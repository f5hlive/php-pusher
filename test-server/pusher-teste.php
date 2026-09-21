<?php
// Incluir autoloader do Composer
// Baixe o nosso composer; caso baixe o oficial MIT, terá que alterar o host no SDK.
// Esse é um testador que verifica conexão com o servidor da PI e mede latência com o servidor atual.
// Caso esteja conectado ao painel da API, receberá um evento em tempo real no canal my-channel. Será aberto modal com a mensagem.
// OU, se desejar ouvir externamente, inscreva-se no canal my-channel e ouça o evento my-event. Rode pusher-teste.php e aguarde o evento. 
require_once __DIR__ . '/vendor/autoload.php';

use Pusher\Pusher;

// === CONFIGURAÇÕES PUSHER ===
 $app_id  = 'SEU_APP_ID';
 $key     = 'SUA_API_KEY';
 $secret  = 'SUA_SECRET_KEY';
 $cluster = 'SEU_CLUSTER';

// IMPORTANTE: Defina aqui o host do seu servidor customizado
// A biblioteca Pusher tenta acessar 'api-custom.pusher.com' por padrão se isto não for definido.
 $custom_host = "api-{$cluster}.f5hlive.com.br";

// === 1. TESTE DE FALLBACK DO SERVIDOR (cURL autenticado) ===
// Monta a URL manualmente para testar conectividade crua
 $test_url = "https://{$custom_host}/apps/{$app_id}/channels";

 $timestamp = time();
 $auth_version = '1.0';
 $method = 'GET';
 $path = "/apps/{$app_id}/channels";

// Prepara parâmetros para autenticação
 $params = [
    'auth_key' => $key,
    'auth_timestamp' => $timestamp,
    'auth_version' => $auth_version,
];

// Ordena parâmetros e monta query string manualmente para evitar problemas de encoding
ksort($params);
 $query_parts = [];
foreach ($params as $k => $v) {
    $query_parts[] = "{$k}={$v}";
}
 $query_string = implode('&', $query_parts);

// Gera a assinatura (HMAC SHA256)
// Formato: "METHOD\nPATH\nQUERY_STRING"
 $string_to_sign = "{$method}\n{$path}\n{$query_string}";
 $auth_signature = hash_hmac('sha256', $string_to_sign, $secret);

// URL final para o cURL
 $full_url = "https://{$custom_host}{$path}?{$query_string}&auth_signature={$auth_signature}";

 $ch = curl_init($full_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
    CURLOPT_SSL_VERIFYPEER => true, // Mantenha true em produção
    CURLOPT_TIMEOUT => 10,
]);
 $response = curl_exec($ch);
 $curl_error = curl_error($ch);
 $info = curl_getinfo($ch);
curl_close($ch);

// === ANALISAR RESPOSTA DO TESTE ===
 $header_size = $info['header_size'];
 $headers = substr($response, 0, $header_size);
 $body = substr($response, $header_size);

 $server_test = [
    'status' => 'error',
    'message' => 'Falha na conexão com o servidor Pusher.',
    'http_code' => $info['http_code'],
    'curl_error' => $curl_error,
    'body' => json_decode($body, true) ?? $body
];
 
if ($info['http_code'] === 200 && !$curl_error) {
    $server_test = [
        'status' => 'ok',
        'message' => 'Servidor Pusher responde corretamente.',
        'http_code' => 200,
        'channels_count' => count(json_decode($body, true)['channels'] ?? []),
        'response_sample' => json_decode($body, true)
    ];
}

// === 2. DISPARO DO EVENTO COM PUSHER  ===
 $options = [
    'cluster' => $cluster, // Mantém o cluster
    'useTLS' => true,      // Força HTTPS //Opcional
    'encrypted' => true,
    // SOLUÇÃO: Define o host customizado explicitamente
    'host' => $custom_host 
];

 $pusher_result = ['status' => 'error', 'message' => 'Pusher não instanciado.'];

try {
    $pusher = new Pusher($key, $secret, $app_id, $options);

    $data = [
        'message' => 'Hello Conect Vendor Load - Teste Fallback ' . date('Y-m-d H:i:s'),
        'test_id' => uniqid(),
        'server_check' => $server_test['status']
    ];

    // O trigger retorna um objeto ou array dependendo da versão, não apenas true/false
    $trigger_result = $pusher->trigger('my-channel', 'my-event', $data);

    // Verificação mais robusta do resultado
    $success = ($trigger_result !== false && (is_array($trigger_result) || is_object($trigger_result)));

    $pusher_result = [
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Evento disparado com sucesso.' : 'Falha no trigger.',
        'payload' => $data,
        'raw_result' => $trigger_result
    ];

} catch (Exception $e) {
    $pusher_result = [
        'status' => 'error',
        'message' => 'Erro Pusher: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString() // Útil para debug
    ];
}

// === 3. RETORNO PERSONALIZADO EM JSON ===
header('Content-Type: application/json');

echo json_encode([
    'pusher_test' => [
        'app_id' => $app_id,
        'cluster' => $cluster,
        'host_used' => $custom_host, // Mostra qual host está sendo usado
        'timestamp' => date('c'),
    ],
    'server_fallback_test' => $server_test,
    'event_trigger' => $pusher_result,
    'debug' => [
        'curl_info' => $info,
        'request_url' => $full_url
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>