<?php
// ── gerar-pix.php — Iron Pay ──────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config-iron.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['error' => 'Dados inválidos']); exit; }

$nome      = trim($input['nome']         ?? 'Cliente');
$email     = trim($input['email']        ?? 'cliente@email.com');
$utm       = trim($input['utm_source']   ?? '');
$utmMed    = trim($input['utm_medium']   ?? '');
$utmCamp   = trim($input['utm_campaign'] ?? '');
$utmTerm   = trim($input['utm_term']     ?? '');
$utmCont   = trim($input['utm_content']  ?? '');
$src       = trim($input['src']          ?? $utm); // fallback pra utm_source
$urlFull   = trim($input['url_full']     ?? '');

function gerarCpf() {
    $n = [];
    for ($i = 0; $i < 9; $i++) $n[] = rand(0, 9);
    $s1 = 0; for ($i = 0; $i < 9; $i++) $s1 += $n[$i] * (10 - $i);
    $d1 = $s1 % 11 < 2 ? 0 : 11 - ($s1 % 11); $n[] = $d1;
    $s2 = 0; for ($i = 0; $i < 10; $i++) $s2 += $n[$i] * (11 - $i);
    $d2 = $s2 % 11 < 2 ? 0 : 11 - ($s2 % 11); $n[] = $d2;
    return implode('', $n);
}

$ddds = ["11","21","31","41","51","61","71","81","85","27"];
$ddd  = $ddds[array_rand($ddds)];
$tel  = $ddd . "9" . str_pad(rand(10000000, 99999999), 8, "0", STR_PAD_LEFT);

$payload = [
    "amount"             => VALOR_CENTAVOS,
    "offer_hash"         => IRONPAY_OFFER_HASH,
    "payment_method"     => "pix",
    "expire_in_days"     => 1,
    "transaction_origin" => "api",
    "postback_url"       => POSTBACK_URL,
    "customer" => [
        "name"         => $nome,
        "email"        => $email,
        "phone_number" => $tel,
        "document"     => gerarCpf(),
        "street_name"  => "Rua das Flores",
        "number"       => "123",
        "complement"   => "",
        "neighborhood" => "Centro",
        "city"         => "São Paulo",
        "state"        => "SP",
        "zip_code"     => "01310100",
    ],
    "cart" => [[
        "product_hash"   => IRONPAY_PRODUCT_HASH,
        "title"          => "Recompensas TikTok",
        "cover"          => null,
        "price"          => VALOR_CENTAVOS,
        "quantity"       => 1,
        "operation_type" => 1,
        "tangible"       => false,
    ]],
    "tracking" => [
        "src"          => $src,
        "utm_source"   => $utm,
        "utm_medium"   => $utmMed,
        "utm_campaign" => $utmCamp,
        "utm_term"     => $utmTerm,
        "utm_content"  => $utmCont,
    ],
];

$url = IRONPAY_BASE_URL . '/transactions?api_token=' . IRONPAY_API_TOKEN;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
file_put_contents('debug-iron.txt', "HTTP: $httpCode\n" . $response);

// Iron Pay retorna o objeto direto (sem wrapper 'data')
// e usa payment_status = 'waiting_payment' como sucesso
if ($httpCode !== 201 || empty($data['hash'])) {
    echo json_encode(['error' => 'Erro ao gerar PIX', 'details' => $data, 'http' => $httpCode]);
    exit;
}

echo json_encode([
    'payment_code' => $data['hash'],
    'pix_code'     => $data['pix']['pix_qr_code'] ?? '',
    'price_label'  => VALOR_LABEL,
    'obrigado_url' => OBRIGADO_URL,
    'status'       => 'pending',
]);