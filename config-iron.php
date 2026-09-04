<?php
// ── config-iron.php — Iron Pay (Front) ───────────────────────

define('IRONPAY_API_TOKEN',    'JKYLJrftu3tbXqDMXiA0XCtlNn1W7Nw96o4L45mqxQtstfET962VB3F9vFHt');
define('IRONPAY_BASE_URL',     'https://api.ironpayapp.com.br/api/public/v1');
define('IRONPAY_OFFER_HASH',   'avbqhpqwte');
define('IRONPAY_PRODUCT_HASH', 'qinqlizbib');

define('VALOR_CENTAVOS',       2083);        // R$ 20,93
define('VALOR_LABEL',          'R$ 20,83');

define('POSTBACK_URL',   'https://solucoesagora.site/webhook/webhook-ironpay.php');
define('OBRIGADO_URL',   'https://solucoesagora.site/klfronttpd/u/up1/checkout.html');