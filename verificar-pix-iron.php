<?php
// ── verificar-pix.php — Iron Pay ─────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config-iron.php';

$code = trim($_GET['code'] ?? '');
if (empty($code)) {
    echo json_encode(['status' => 'pending']); exit;
}

$url = IRONPAY_BASE_URL . '/transactions/' . urlencode($code) . '?api_token=' . IRONPAY_API_TOKEN;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
curl_close($ch);

$data   = json_decode($response, true);
$status = $data['payment_status'] ?? 'pending';

// Iron Pay usa "paid" — mapeia para "approved" que o frontend espera
$mapped = ($status === 'paid') ? 'approved' : 'pending';

echo json_encode([
    'status'       => $mapped,
    'obrigado_url' => OBRIGADO_URL,
]);