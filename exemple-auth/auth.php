<?php
// ============================================================
// Endpoint de autorização de canal (presence / private)
// ============================================================
// Apenas um exemplo básico de valiação com Auth

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------- CONFIG ----------
$API_KEY    = 'SUA_API_KEY';
$API_SECRET = 'SUA_SECRET_KEY';
// ----------------------------

$socketId = $_POST['socket_id'] ?? '';
$channel  = $_POST['channel_name'] ?? '';

if (!$socketId || !$channel) {
    http_response_code(400);
    echo json_encode(['error' => 'socket_id e channel_name são obrigatórios']);
    exit;
}

// Gera a assinatura no formato que o server Pusher/compatible espera
$stringToSign = $socketId . ':' . $channel;
$signature    = hash_hmac('sha256', $stringToSign, $API_SECRET);
$auth         = $API_KEY . ':' . $signature;

// Resposta básica (funciona para private e presence)
$response = [
    'auth' => $auth
];

// Se for canal presence, precisa de channel_data
if (strpos($channel, 'presence-') === 0) {
    // Dados do usuário (obrigatório em presence)
    $userData = [
        'user_id'   => 'user-' . substr(md5($socketId), 0, 8), // ID único por conexão
        'user_info' => [
            'name' => 'Visitante'
        ]
    ];

    $channelData = json_encode($userData);
    $stringToSignPresence = $socketId . ':' . $channel . ':' . $channelData;
    $signaturePresence    = hash_hmac('sha256', $stringToSignPresence, $API_SECRET);

    $response = [
        'auth'         => $API_KEY . ':' . $signaturePresence,
        'channel_data' => $channelData
    ];
}

echo json_encode($response);