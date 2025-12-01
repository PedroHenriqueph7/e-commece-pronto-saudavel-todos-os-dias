<?php
// config/pagseguro_notification.php

// CARREGA CONFIG E CONEXÃO COM O BANCO
$config = require __DIR__ . '/pagseguro.php';

// Ajuste este require para o caminho real da sua conexão PDO.
// Se seu arquivo de conexão estiver em outro local, ajuste o caminho aqui.
// Exemplo (se você mantiver a conexão em app/core):
// require __DIR__ . '/../app/core/DataBaseConecta.php';
require __DIR__ . '/DataBaseConecta.php'; // <-- ajuste conforme seu projeto

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    echo "Erro: conexão PDO não definida.";
    exit;
}

// AMBIENTE E CREDENCIAIS
$env     = $config['environment'] ?? 'sandbox';
$baseUrl = $config[$env]['base_url'] ?? '';
$apiKey  = $config[$env]['api_key'] ?? '';

if (empty($apiKey)) {
    file_put_contents(__DIR__ . "/logs_pagbank_errors.txt", "[" . date("Y-m-d H:i:s") . "] API key não configurada no config/pagseguro.php\n", FILE_APPEND);
    http_response_code(500);
    echo "API key não configurada.";
    exit;
}

// RECEBER JSON DO PAGBANK
$body = file_get_contents("php://input");
$data = json_decode($body, true);

if ($data === null) {
    http_response_code(400);
    echo "Invalid JSON";
    exit;
}

// PEGAR ID DO PEDIDO OU CHARGE OU REFERENCE
$orderId = $data['order_id'] ?? $data['charge_id'] ?? $data['id'] ?? null;
$referenceId = $data['reference_id'] ?? null;

if (!$orderId && !$referenceId) {
    http_response_code(400);
    echo "Missing identifier (order_id / charge_id / reference_id)";
    exit;
}

// Se tivermos only referenceId (padrão): tentar buscar order via reference pode não ser suportado.
// Prioriza orderId se existir.
$lookupId = $orderId ?: $referenceId;

// CONSULTAR DETALHES DO PEDIDO NA API (se tivermos orderId)
// se não tiver orderId, esta etapa pode ser pulada dependendo do payload do webhook
$orderData = null;
if ($orderId) {
    $endpoint = rtrim($baseUrl, '/') . '/orders/' . urlencode($orderId);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        file_put_contents(__DIR__ . "/logs_pagbank_errors.txt",
            "[" . date("Y-m-d H:i:s") . "] Falha ao consultar pedido {$orderId}: HTTP {$httpCode} - curlErr: {$curlErr} - resp: {$response}\n",
            FILE_APPEND
        );
        // Retornamos 500 para que o PagBank tente reenviar a notificação
        http_response_code(500);
        echo "Failed to consult order";
        exit;
    }

    $orderData = json_decode($response, true);
    if ($orderData === null) {
        http_response_code(500);
        echo "Invalid API response";
        exit;
    }
}

// EXTRAIR STATUS DO PAGBANK
$realStatus = null;

// tentar em vários locais (charges, payment, status)
if (!empty($orderData)) {
    $realStatus =
        $orderData['charges'][0]['status']
        ?? $orderData['charges'][0]['payment_status']
        ?? $orderData['status']
        ?? null;
}

// fallback para dados diretos do webhook (algumas notificações trazem status)
if (!$realStatus) {
    $realStatus = $data['status'] ?? $data['payment_status'] ?? null;
}

if (!$realStatus) {
    $realStatus = 'UNKNOWN';
}

// MAPEAR STATUS PARA O BANCO
$mapStatus = [
    'PAID'         => 'pago',
    'AUTHORIZED'   => 'autorizado',
    'DECLINED'     => 'recusado',
    'CANCELLED'    => 'cancelado',
    'REFUNDED'     => 'estornado',
    'IN_ANALYSIS'  => 'analise',
    'WAITING'      => 'pendente'
];

$statusFinal = $mapStatus[$realStatus] ?? strtolower($realStatus);

// ATUALIZAR PEDIDO NO BANCO PELO REFERENCE_ID (ou pelo reference salvo como id do pedido)
$idParaAtualizar = $referenceId ?: $lookupId;

try {
    // Exemplo: sua tabela 'pedido' deve usar 'id' que bate com reference_id (ou adapte a query)
    $sql = "UPDATE pedido SET status = :status WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $statusFinal,
        ':id'     => $idParaAtualizar
    ]);

} catch (Exception $e) {
    file_put_contents(__DIR__ . "/logs_pagbank_errors.txt",
        "[" . date("Y-m-d H:i:s") . "] DB Error for identifier {$idParaAtualizar}: " . $e->getMessage() . "\n",
        FILE_APPEND
    );

    http_response_code(500);
    echo "DB Error";
    exit;
}

// LOG DE SUCESSO
file_put_contents(
    __DIR__ . "/logs_pagbank.txt",
    "[" . date("Y-m-d H:i:s") . "] Pedido {$idParaAtualizar} atualizado para {$realStatus} ({$statusFinal})\n",
    FILE_APPEND
);

// RESPOSTA FINAL
http_response_code(200);
echo "OK";
