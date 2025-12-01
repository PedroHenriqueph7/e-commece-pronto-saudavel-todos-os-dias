<?php
// config/pagseguro_redirect.php

$checkoutId = $_GET['checkout_id'] ?? null;
$orderId    = $_GET['order_id'] ?? null;
$status     = $_GET['status'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Status do Pagamento</title>
</head>
<body>
    <h1>Retorno do Pagamento</h1>

    <p>Obrigado por comprar conosco.</p>

    <?php if ($orderId): ?>
        <p><strong>ID do Pedido:</strong> <?= htmlspecialchars($orderId) ?></p>
    <?php endif; ?>

    <?php if ($checkoutId): ?>
        <p><strong>ID do Checkout:</strong> <?= htmlspecialchars($checkoutId) ?></p>
    <?php endif; ?>

    <?php if ($status): ?>
        <p><strong>Status inicial informado:</strong> <?= htmlspecialchars($status) ?></p>
    <?php endif; ?>

    <?php if (!$orderId && !$checkoutId && !$status): ?>
        <p>Nenhum parâmetro foi enviado pelo PagBank. Isso é normal em alguns fluxos.</p>
    <?php endif; ?>

    <p>O status FINAL do pagamento será confirmado automaticamente via notificação (webhook).</p>
</body>
</html>
