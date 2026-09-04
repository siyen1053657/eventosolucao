<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
require_once 'config-paradise.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$nome  = trim($input['nome']  ?? '');
$email = trim($input['email'] ?? '');

if (empty($email)) {
    $ts  = round(microtime(true) * 1000);
    $rnd = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6);
    $email = "cliente_{$ts}_{$rnd}@mail.com";
}

function gerarCpf() {
    $n = [];
    for ($i = 0; $i < 9; $i++) $n[] = rand(0, 9);
    $s1 = 0; for ($i = 0; $i < 9; $i++) $s1 += $n[$i] * (10 - $i);
    $d1 = $s1 % 11 < 2 ? 0 : 11 - ($s1 % 11);
    $n[] = $d1;
    $s2 = 0; for ($i = 0; $i < 10; $i++) $s2 += $n[$i] * (11 - $i);
    $d2 = $s2 % 11 < 2 ? 0 : 11 - ($s2 % 11);
    $n[] = $d2;
    return implode("", $n);
}

$ddds = ["11","21","31","41","51","61","71","81","85","27"];
$ddd  = $ddds[array_rand($ddds)];
$tel  = $ddd . "9" . str_pad(rand(10000000, 99999999), 8, "0", STR_PAD_LEFT);

// UTMs e tracking
$tracking = [];
$campos = ["utm_source","utm_medium","utm_campaign","utm_content","utm_term","src","sck"];
foreach ($campos as $c) {
    if (!empty($input[$c])) $tracking[$c] = $input[$c];
}

$amount = isset($input['taxa_centavos']) && is_numeric($input['taxa_centavos'])
    ? (int) $input['taxa_centavos']
    : PAYMENT_AMOUNT;

$reference = "front_" . uniqid();

$payload = [
    "amount"       => $amount,
    "description"  => "Front",
    "reference"    => $reference,
    "name"         => "Front",
    "productHash"  => PARADISE_PRODUCT,
    "postback_url" => POSTBACK_URL,
    "customer"     => [
        "name"     => $nome ?: "Cliente",
        "email"    => $email,
        "document" => gerarCpf(),
        "phone"    => $tel,
    ],
];
if (!empty($tracking)) $payload["tracking"] = $tracking;

// ── xTracky — waiting_payment ──────────────────────────────
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

$ch = curl_init(PARADISE_BASE_URL . "/api/v1/transaction.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "X-API-Key: " . PARADISE_API_KEY,
        "Content-Type: application/json",
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT    => 30,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(["error" => "Erro de conexão com gateway"]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || ($data["status"] ?? "") !== "success") {
    http_response_code(502);
    echo json_encode(["error" => "Erro ao gerar PIX", "details" => $data]);
    exit;
}

// xTracky — waiting_payment
sendXTracky('waiting_payment', (string)$data["transaction_id"], $amount, $nome, $email);

echo json_encode([
    "payment_code" => (string) $data["transaction_id"],
    "pix_code"     => $data["qr_code"] ?? "",
    "status"       => "pending",
    "obrigado_url" => OBRIGADO_URL,
    "price_label"  => "R$ " . number_format($amount / 100, 2, ",", "."),
]);