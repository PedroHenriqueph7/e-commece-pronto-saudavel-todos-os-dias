<?php
// config/FinalizarCompraController.php

session_start();

// Carrega config e classe do PagSeguro (ambos dentro de config/)
$config = require __DIR__ . '/pagseguro.php';
require_once __DIR__ . '/PagSeguroController.php';

// 1. Verifica login
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    http_response_code(403);
    die("Você precisa estar logado para finalizar a compra.");
}

$usuario = $_SESSION['usuario']; 
// Exemplo esperado:
// ['nome' => 'Fulano', 'email' => 'fulano@ex.com']


// 2. Verifica carrinho
if (!isset($_SESSION['carrinho']) || empty($_SESSION['carrinho'])) {
    die("Carrinho vazio! Adicione itens para continuar.");
}


// 3. Monta os itens no formato do PagBank
$itens = [];

foreach ($_SESSION['carrinho'] as $prod) {

    // Valida campos essenciais
    if (!isset($prod['id'], $prod['nome'], $prod['preco'], $prod['quantidade'])) {
        die("Carrinho contém item inválido.");
    }

    // Converte valor para centavos
    $valorCentavos = (int) round($prod['preco'] * 100);

    $itens[] = [
        'sku'         => $prod['id'],
        'name'        => $prod['nome'],
        'quantity'    => (int)$prod['quantidade'],
        'unit_amount' => $valorCentavos
    ];
}


// 4. Monta o pedido
$pedido = [
    'id'   => 'pedido_' . time(),
    'items' => $itens,
    'payer' => [
        'name'  => $usuario['nome'],
        'email' => $usuario['email']
    ]
];


// 5. Envia para PagSeguro / PagBank
try {
    $pag = new PagSeguroController($config);
    $urlPagamento = $pag->createCheckout($pedido);

    // Redireciona para o checkout oficial do PagBank
    header("Location: " . $urlPagamento);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Erro ao iniciar pagamento: " . htmlspecialchars($e->getMessage());
}
