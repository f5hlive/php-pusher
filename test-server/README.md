# F5HLIVE - Diagnóstico de Conexão e Disparo de Eventos (PHP)

Este repositório contém um script de diagnóstico ponta-a-ponta para validar a conectividade com os clusters da **F5HLIVE** e testar a latência do servidor. 

Ele atua em duas camadas de verificação:

1. **Teste Raw (cURL):** Testa a API REST diretamente montando a assinatura HMAC SHA256 na mão. Isso prova se o servidor está acessível, validando DNS, portas e bloqueios de firewall, independentemente de dependências externas.
2. **Teste SDK (Pusher PHP):** Instancia a biblioteca, injeta o host customizado do seu cluster e dispara um evento real (`my-event` no canal `my-channel`), validando a entrega do payload.

## Requisitos

* PHP 7.4+ ou 8.x
* Extensão cURL habilitada
* Composer

> **Aviso Importante sobre o SDK:** Você deve utilizar o nosso pacote Composer customizado. Caso opte por baixar a versão oficial MIT do Pusher, será estritamente necessário injetar o parâmetro `'host' => $custom_host` nas opções de inicialização (como demonstrado no script) para evitar que a biblioteca tente rotear o tráfego para os servidores públicos em vez do seu cluster F5HLIVE.

## Configuração

Antes de rodar o teste, abra o arquivo PHP e insira as credenciais do seu painel:

```php
$app_id  = 'SEU_APP_ID';
$key     = 'SUA_API_KEY';
$secret  = 'SUA_SECRET_KEY';
$cluster = 'SEU_CLUSTER'; // ex: ca, de, br
```
O host será montado automaticamente no formato: api-{cluster}.f5hlive.com.br.

## Como Usar
Rode o script diretamente no navegador ou via terminal embutido do PHP:

php -S localhost:8000
# Acesse http://localhost:8000/pusher-teste.php

Para escutar o evento chegando em tempo real, você pode:

1. Manter o seu Painel da API aberto (o modal de teste aparecerá na tela).

2. Rodar um front-end de teste escutando o canal my-channel e o evento my-event.

## Estrutura de Retorno (JSON)
O script devolve um log detalhado, ideal para debug rápido de infraestrutura. Se a conexão for bem-sucedida, você verá o seguinte retorno:

```JSON
{
    "pusher_test": {
        "app_id": "123456",
        "cluster": "ca",
        "host_used": "api-ca.f5hlive.com.br",
        "timestamp": "2026-09-21T11:26:24-03:00"
    },
    "server_fallback_test": {
        "status": "ok",
        "message": "Servidor Pusher responde corretamente.",
        "http_code": 200,
        "channels_count": 5
    },
    "event_trigger": {
        "status": "ok",
        "message": "Evento disparado com sucesso.",
        "payload": {
            "message": "Hello Conect Vendor Load - Teste Fallback 2026-09-21 11:26:24",
            "test_id": "64f8a9b2...",
            "server_check": "ok"
        }
    }
}
```