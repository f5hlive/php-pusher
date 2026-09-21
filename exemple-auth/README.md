```php
$API_KEY    = 'SUA_API_KEY';
$API_SECRET = 'SEU_API_SECRET';
$APP_ID     = 'SEU_APP_ID';
// Produção: https://api-[SEU_CLUSTER].f5hlive.com.br
// RECOMENDADO! Sempre informe o prefixo do seu cluster atual (ex: api-ca).
// Caso contrário, a requisição cairá no cluster padrão (ex: api-de),
// causando erro de leitura se o socket do usuário estiver fisicamente 
// conectado em outra região (ws-ca, sse-ca, sockjs-ca, etc).
```

## Como usar no front

```js
## Invocando o auth

Exemplo:

pusher = new Pusher(APP_KEY, {
    cluster: 'custom',
    channelAuthorization: {
    endpoint: '/sua_pasta/auth.php',
    transport: 'ajax'
    }
});
```

```js
Requer Auth configurado /sua_pasta/auth.php
const endpoint = new URL(STATUS_PROXY_URL, window.location.origin);
endpoint.searchParams.set('socket_id', socketId);
endpoint.searchParams.set('channel', 'presence-canais-demo');
const data = await (await fetch(endpoint)).json();
// data = { statusSocketid, countChannel, countSocketidChannel, user_count }
```