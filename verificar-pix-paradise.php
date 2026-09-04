<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
require_once 'config-paradise.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$transactionId = trim($_GET['code'] ?? '');
if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Código inválido']);
    exit;
}

// ── xTracky ──────────────────────────────────────────────────
function sendXTracky($status, $orderId, $amount, $nome, $email) {
    $token = defined('XTRACKY_TOKEN') ? XTRACKY_TOKEN : '';
    if (empty($token)) return;
    $payload = json_encode([
        'orderId'      => $orderId ?: ('order-' . time()),
        'amount'       => (int) $amount,
        'status'       => $status,
        'platform'     => 'CUSTOM',
        'utm_source'   => $token,
        'leadName'     => $nome  ?: '',
        'leadEmail'    => $email ?: '',
        'leadDocument' => '',
    ]);
    $ch = curl_init('https://api.xtracky.com/api/integrations/api');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

$url = PARADISE_BASE_URL . '/api/v1/query.php?action=get_transaction&id=' . urlencode($transactionId);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-API-Key: ' . PARADISE_API_KEY],
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Erro ao consultar pagamento']);
    exit;
}

$data   = json_decode($response, true);
$status = $data['status'] ?? 'pending';
$mapped = ($status === 'approved') ? 'approved' : 'pending';

if ($mapped === 'approved') {
    sendXTracky('paid', $transactionId, PAYMENT_AMOUNT, '', '');
}

echo json_encode([
    'status'       => $mapped,
    'obrigado_url' => OBRIGADO_URL,
]);