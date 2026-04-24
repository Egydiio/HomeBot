# WhatsApp Service

Serviço Node.js que conecta no WhatsApp Web com `whatsapp-web.js`, normaliza mensagens recebidas e encaminha o payload para o Laravel via webhook interno.

## Endpoints

- `GET /health`
- `POST /send-message`

## Fluxo

1. O serviço inicia o `Client` com `LocalAuth`
2. Na primeira execução, imprime o QR Code no terminal
3. Quando chega mensagem individual, normaliza o payload
4. Se houver mídia suportada, envia base64 para o Laravel
5. O Laravel processa a regra de negócio e responde usando `POST /send-message`

## Variáveis

Copie `.env.example` para `.env` e ajuste os tokens compartilhados com o Laravel.

## Logs úteis

- `QR recebido`
- `Cliente autenticado`
- `Cliente pronto`
- `Cliente desconectado`
- `Falha no webhook`
