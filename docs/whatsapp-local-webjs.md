# WhatsApp local com whatsapp-web.js

## Por que o MVP usa essa abordagem

Para o MVP, o HomeBot trocou a dependência direta da Z-API por um adapter local gratuito com `whatsapp-web.js`. Isso reduz custo agora e preserva o core do bot atrás de `WhatsAppClientInterface`.

## Arquitetura

```text
WhatsApp real
  ↓
whatsapp-service (Node + whatsapp-web.js)
  ↓ HTTP POST
Laravel /api/webhook/whatsapp
  ↓
WebhookController → BotRouter → Handlers/Jobs
  ↓ HTTP POST
whatsapp-service /send-message
  ↓
WhatsApp real
```

## Limitações

- não é API oficial
- a sessão pode cair
- pode ser necessário escanear o QR novamente
- não é ideal para escala

## Como rodar

```bash
docker compose up -d
docker compose logs -f whatsapp-service
```

## Como parear o WhatsApp

1. Suba o ambiente
2. Abra os logs do `whatsapp-service`
3. Escaneie o QR Code exibido
4. Aguarde os logs `Cliente autenticado` e `Cliente pronto`

## Teste manual de envio

```bash
curl -X POST http://localhost:3000/send-message \
  -H "Authorization: Bearer change-me" \
  -H "Content-Type: application/json" \
  -d '{"phone":"5531999999999","message":"teste do HomeBot"}'
```

## Como o Node chama o Laravel

Quando uma mensagem individual chega:

1. o `whatsapp-service` ignora `fromMe`
2. ignora grupos por padrão
3. normaliza remetente, texto e tipo da mensagem
4. quando houver mídia suportada, envia base64 para o Laravel
5. chama `LARAVEL_WEBHOOK_URL` com `X-HomeBot-Webhook-Token`

## Segurança mínima

- `POST /send-message` exige `Authorization: Bearer`
- o Laravel valida `X-HomeBot-Webhook-Token`
- sessões do WhatsApp não entram no Git
- mídias recebidas são salvas no storage local privado do Laravel

## Checklist manual

- subir os containers
- escanear o QR Code
- mandar `oi`
- validar resposta de menu
- mandar `saldo`
- validar resposta de saldo
- mandar imagem de nota fiscal
- verificar `Transaction` e disparo de job
- verificar logs do Laravel e do `whatsapp-service`
- testar `POST /send-message`
- reiniciar `whatsapp-service` e confirmar persistência da sessão

## Como migrar para API oficial depois

O Laravel agora depende de `App\Services\WhatsApp\WhatsAppClientInterface`. Para trocar o adapter:

1. criar um novo client, como `MetaWhatsAppCloudClient`
2. implementar `sendText()`
3. ajustar o binding pelo `WHATSAPP_DRIVER`
4. manter o webhook normalizado para o `WebhookController`
