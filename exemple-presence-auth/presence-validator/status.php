<?php
// ============================================================
// Proxy assinado — GET /apps/:app_id/realtime/status
// ============================================================
// Este arquivo roda NO SERVIDOR (PHP). O navegador só chama esta URL e
// recebe o JSON. O api_secret NUNCA vai para o navegador — é usado aqui,
// do lado do servidor, para assinar a chamada à API REST do pusher.
//
// Uso no front (canais.html):
//   STATUS_PROXY_URL = '/sua_pasta/status.php'
//   (ou o caminho absoluto se a página estiver em outro domínio)
//
// Query params aceitos (repassados ao servidor):
//   socket_id  (opcional) → statusSocketid on/off
//   channel    (opcional) → countSocketidChannel + user_count
// ============================================================

// ---------- CONFIG (troque pelos valores da sua app) ----------
$API_KEY    = 'SUA_API_KEY';
$API_SECRET = 'SUA_SECRET_KEY';
$APP_ID     = 'SEU_APP_ID';
$SERVER_URL = 'https://api-custom.f5hlive.com.br:443';
// Produção: https://api-[SEU_CLUSTER].f5hlive.com.br
// RECOMENDADO! Sempre informe o prefixo do seu cluster atual (ex: api-ca).
// Caso contrário, a requisição cairá no cluster padrão (ex: api-de),
// causando erro de leitura se o socket do usuário estiver fisicamente 
// conectado em outra região (ws-ca, sse-ca, sockjs-ca, etc).
// --------------------------------------------------------------

$socketId = isset($_GET['socket_id']) ? (string) $_GET['socket_id'] : '';
$channel  = isset($_GET['channel'])   ? (string) $_GET['channel']   : '';

$path = '/apps/' . $APP_ID . '/realtime/status';

// Parâmetros que entram na assinatura.
$authParams = [
    'auth_key'       => $API_KEY,
    'auth_timestamp' => (string) time(),
    'auth_version'   => '1.0',
];
ksort($authParams);

$queryNoSig    = http_build_query($authParams, '', '&', PHP_QUERY_RFC3986);
$stringToSign  = "GET\n" . $path . "\n" . $queryNoSig;
$authSignature = hash_hmac('sha256', $stringToSign, $API_SECRET);

// Monta a URL final com tudo (assinatura + params opcionais).
$allParams = array_merge($authParams, [
    'auth_signature' => $authSignature,
    'socket_id'      => $socketId,
    'channel'        => $channel,
]);
$url = $SERVER_URL . $path . '?' . http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);

// Chama o servidor real.
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false, // https autoassinado
    CURLOPT_SSL_VERIFYHOST => false,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Falha ao chamar o servidor: ' . $curlError]);
    exit;
}

http_response_code($httpCode);
echo $response;
