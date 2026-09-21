# Presence Validator — Proxy PHP assinado

Proxy PHP que assina a API REST `GET /apps/:app_id/realtime/status` do servidor,
para o front consultar `statusSocketid`, `countChannel`, `countSocketidChannel` e
`user_count` **sem expor o api_secret no navegador**.

## Arquivos

- `status.php` — o proxy. Assina a chamada e repassa ao servidor.

## Configuração (dentro de `status.php`)

```php
$API_KEY    = 'SUA_API_KEY';
$API_SECRET = 'SEU_API_SECRET';
$APP_ID     = 'SEU_APP_ID';
$SERVER_URL = 'http://127.0.0.1:3000';
// Produção: https://api-[SEU_CLUSTER].f5hlive.com.br
// RECOMENDADO! Sempre informe o prefixo do seu cluster atual (ex: api-ca).
// Caso contrário, a requisição cairá no cluster padrão (ex: api-de),
// causando erro de leitura se o socket do usuário estiver fisicamente 
// conectado em outra região (ws-ca, sse-ca, sockjs-ca, etc).
```

## Como usar no front

```js
const STATUS_PROXY_URL = '/sua_pasta/presence-validator/status.php';

const endpoint = new URL(STATUS_PROXY_URL, window.location.origin);
endpoint.searchParams.set('socket_id', socketId);
endpoint.searchParams.set('channel', 'presence-canais-demo');
const data = await (await fetch(endpoint)).json();
// data = { statusSocketid, countChannel, countSocketidChannel, user_count }
```

```js
const STATUS_PROXY_URL = '/sua_pasta/status.php';

Exemplo: 
channelAuthorization: {
  endpoint: '/sua_pasta/auth.php',
  transport: 'ajax'
}

const endpoint = new URL(STATUS_PROXY_URL, window.location.origin);
endpoint.searchParams.set('socket_id', socketId);
endpoint.searchParams.set('channel', 'presence-canais-demo');
const data = await (await fetch(endpoint)).json();
// data = { statusSocketid, countChannel, countSocketidChannel, user_count }
```

```js
Requer Auth configurado /sua_pasta/auth.php
const endpoint = new URL(STATUS_PROXY_URL, window.location.origin);
endpoint.searchParams.set('socket_id', socketId);
endpoint.searchParams.set('channel', 'presence-canais-demo');
const data = await (await fetch(endpoint)).json();
// data = { statusSocketid, countChannel, countSocketidChannel, user_count }
```

## Como testar localmente 

1. Acesse a página `/sua_pasta/canais.html` — ela já aponta pro proxy.
2. Abra duas abas da página e veja os contadores atualizarem.

Ou rode o PHP standalone pelo terminal:

```
php -S 127.0.0.1:8080 -t /sua_pasta/
```

e chame:

```
curl "http://127.0.0.1:8080/status.php?socket_id=ABC&channel=presence-canais-demo"
```

## Resposta esperada

```json
{
  "statusSocketid": "on",
  "countChannel": 1,
  "countSocketidChannel": 1,
  "user_count": 1
}
```

## Notas

- `statusSocketid`: "on" | "off" — socket_id está conectado?
- `countChannel`: total de canais abertos na app (WS + SockJS + SSE).
- `countSocketidChannel`: sockets inscritos no canal informado.
- `user_count`: usuários únicos no canal presence.
- A assinatura segue o mesmo padrão HMAC-SHA256 dos endpoints `/apps/:app_id/events`
  existentes: `GET\n<path>\nauth_key=...&auth_timestamp=...&auth_version=1.0`.


- `statusSocketid`: "on" | "off" = Recebe um sinal do servidor informando se o Socket ID está ou não conectado.

ON está conectado, off já desconectou e saiu. Servidor não armazena sockets ID antigos; sempre que uma nova conexão

É realizado um novo socketID é gerado.

- `countChannel`: O servidor retorna quantos canais estão abertos atualmente na sua API (WS + SockJS + SSE). Exemplo: API = 50 channels."

- `countSocketidChannel`: Aqui você obtém dados relacionados aos canais, retorna quantos socketids estão conectados ao canal, ex.: my-channel = 50." 

- `user_count`: Usuários únicos no canal presence.

Ideal para monitorar dados e conexões. Se precisa para chats, lives, salas, apps, grupos, métricas de dashboard, essa seria uma implementação perfeita.

Não exige refatorar código, entrega dados em tempo real sem necessidade de programar do seu lado, atualiza em tempo real.  

## Teste final

```
Exemplo Status php

<?php
// ============================================================
// Proxy assinado — GET /apps/:app_id/realtime/status
// ============================================================
// Este arquivo roda NO SERVIDOR (PHP). O navegador só chama esta URL e
// recebe o JSON. O api_secret NUNCA vai para o navegador — é usado aqui,
// do lado do servidor, para assinar a chamada à API REST do pusher.
//
// Uso no front (canais.html):
//   STATUS_PROXY_URL = '/sua pasta/status.php'
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

````

```

Exemplo Auth php

<?php
// ============================================================
// Endpoint de autorização de canal (presence / private)
// ============================================================

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

// Gera a assinatura no formato que o Pusher/compatible espera
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

```

```
Exemplo javascript

<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Teste de Presence</title>
  <script src="https://sdk.f5hlive.com.br/pusher/8.5.0/pusher.min.js"></script>
  <style>
    :root {
      color-scheme: dark;
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #10131a;
      color: #eef2ff;
    }

    body { margin: 0; padding: 24px; }
    main { max-width: 80%; margin: 0 auto; }
    h1 { margin: 0 0 6px; font-size: 1.35rem; }
    p { color: #aab4ca; line-height: 1.5; }
    code { color: #9bc1ff; }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
      gap: 12px;
      margin: 22px 0 14px;
    }

    .card {
      background: #191e29;
      border: 1px solid #2d3548;
      border-radius: 12px;
      padding: 16px;
      min-height: 82px;
    }

    .label { color: #aab4ca; font-size: .8rem; }
    .value { display: block; margin-top: 8px; font-size: 1.35rem; font-weight: 700; }
    .on { color: #61d7a8; }
    .off, .error { color: #ff8f9b; }
    .muted { color: #aab4ca; font-size: .85rem; }

    .actions { display: flex; gap: 10px; flex-wrap: wrap; margin: 16px 0; }
    button {
      border: 0;
      border-radius: 8px;
      padding: 10px 14px;
      background: #4b7dff;
      color: #fff;
      font: inherit;
      cursor: pointer;
    }
    button:hover { background: #3d6bea; }

    #log {
      min-height: 78px;
      max-height: 220px;
      overflow: auto;
      padding: 12px;
      border: 1px solid #2d3548;
      border-radius: 10px;
      background: #0c0f15;
      color: #b9c3d8;
      font: .82rem/1.55 ui-monospace, SFMono-Regular, Consolas, monospace;
      white-space: pre-wrap;
    }
  </style>
</head>
<body>
  <main>
    <h1>Presence: teste de contadores</h1>
    <p>
      Canal: <code id="channel-name">presence-canais-demo</code>. Abra esta página em outra aba
      para validar entrada, saída e os contadores de membros.
    </p>

     <section class="grid" aria-label="Contadores realtime">
      <article class="card">
        <span class="label">statusSocketid</span>
        <strong id="socket-status" class="value off">off</strong>
      </article>
      <article class="card">
        <span class="label">socket_id</span>
        <strong id="socket-id" class="value muted">—</strong>
      </article>
      <article class="card">
        <span class="label">Presence users (realtime)</span>
        <strong id="presence-count" class="value">0</strong>
      </article>
      <article class="card">
        <span class="label">Conectados no canal (realtime)</span>
        <strong id="api-socket-count" class="value">0</strong>
      </article>
      <article class="card">
        <span class="label">countChannel (API)</span>
        <strong id="api-channel-count" class="value muted">—</strong>
      </article>
    </section>

    <p id="api-note" class="muted">
      Conectados / Presence users vêm em tempo real do SDK (sem API). O countChannel
      (total de canais no servidor) precisa de um proxy assinado do backend — a API REST
      é assinada e o secret não deve ir para o navegador.
    </p>

    <div class="actions">
      <button type="button" id="refresh-status">Atualizar countChannel da API</button>
      <button type="button" id="reconnect">Reconectar</button>
    </div>

    <div id="log" aria-live="polite"></div>
  </main>

<script>

    // Ajuste somente estes valores para a sua chave e canal de teste.
    const APP_KEY = ' ..... '; // ← troque pela sua api_key real
    const CHANNEL_NAME = 'presence-canais-demo';

    // Proxy PHP que assina GET /apps/:app_id/realtime/status (o secret fica no servidor).
    // Local (Laragon): '/sua_pasta/presence-validator/status.php'
    // Produção: troque pelo caminho real do proxy no seu domínio.
    const STATUS_PROXY_URL = '/sua_pasta/status.php';

    Pusher.logToConsole = true;
    document.querySelector('#channel-name').textContent = CHANNEL_NAME;

    const socketStatus = document.querySelector('#socket-status');
    const socketId = document.querySelector('#socket-id');
    const presenceCount = document.querySelector('#presence-count');
    const apiSocketCount = document.querySelector('#api-socket-count');
    const apiChannelCount = document.querySelector('#api-channel-count');
    const apiNote = document.querySelector('#api-note');
    const log = document.querySelector('#log');

    let pusher;
    let channel;

    function writeLog(message, type = '') {
      const line = document.createElement('div');
      line.className = type;
      line.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
      log.prepend(line);
    }

    function setSocketStatus(online) {
      socketStatus.textContent = online ? 'on' : 'off';
      socketStatus.className = `value ${online ? 'on' : 'off'}`;
      socketId.textContent = online ? (pusher.connection.socket_id || '—') : '—';
    }

    // Contagem em TEMPO REAL: vem do SDK, sem API assinada.
    // presence.members.count = usuários únicos no canal (alimentado pelo server
    // via subscription_succeeded + member_added/member_removed).
    function updateRealtimeCounts() {
      const count = channel && channel.members ? channel.members.count : 0;
      presenceCount.textContent = String(count);
      apiSocketCount.textContent = String(count);
    }

    async function refreshChannelCountApi() {
      if (!STATUS_PROXY_URL) {
        apiNote.textContent = 'countChannel precisa de STATUS_PROXY_URL (proxy assinado do backend). Os contadores de conectados já aparecem em tempo real acima.';
        return;
      }

      try {
        const endpoint = new URL(STATUS_PROXY_URL, window.location.origin);
        endpoint.searchParams.set('socket_id', pusher.connection.socket_id || '');
        endpoint.searchParams.set('channel', CHANNEL_NAME);
        const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);

        apiChannelCount.textContent = data.countChannel ?? '—';
        apiNote.textContent = `API: canais abertos=${data.countChannel ?? 0} · socket ${data.statusSocketid || 'off'}`;
        writeLog(`API: countChannel=${data.countChannel ?? 0}, sockets no canal=${data.countSocketidChannel ?? 0}`);
      } catch (error) {
        apiNote.textContent = `Falha na API: ${error.message}`;
        writeLog(`Falha ao consultar API: ${error.message}`, 'error');
      }
    }

    function connect() {
      if (pusher) pusher.disconnect();

      pusher = new Pusher(APP_KEY, {
        cluster: 'custom',
        channelAuthorization: {
          endpoint: '/sua_pasta/auth.php',
          transport: 'ajax'
        }
      });

      pusher.connection.bind('state_change', ({ current }) => {
        const online = current === 'connected';
        setSocketStatus(online);
        writeLog(`Conexão: ${current}`, online ? 'on' : '');
        if (online) refreshChannelCountApi();
      });

      channel = pusher.subscribe(CHANNEL_NAME);
      channel.bind('pusher:subscription_succeeded', () => {
        updateRealtimeCounts();
        writeLog(`Inscrito em ${CHANNEL_NAME}; conectados=${channel.members.count}`, 'on');
        refreshChannelCountApi();
      });
      channel.bind('pusher:member_added', member => {
        updateRealtimeCounts();
        writeLog(`Entrou: ${member.id}; conectados=${channel.members.count}`, 'on');
      });
      channel.bind('pusher:member_removed', member => {
        updateRealtimeCounts();
        writeLog(`Saiu: ${member.id}; conectados=${channel.members.count}`);
      });
      channel.bind('pusher:subscription_error', error => {
        writeLog(`Erro de inscrição: ${JSON.stringify(error)}`, 'error');
      });
    }

    document.querySelector('#refresh-status').addEventListener('click', refreshChannelCountApi);
    document.querySelector('#reconnect').addEventListener('click', connect);
    connect();
  </script>
  
  </body>
</html>
  
 ``` 