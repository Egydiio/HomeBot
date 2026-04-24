# HomeBot

HomeBot é um SaaS brasileiro de gestão financeira doméstica via WhatsApp. O bot recebe nota ou conta, registra a despesa, atualiza o saldo entre os moradores e fecha o mês com cobrança Pix.

## Stack

- Laravel 13
- Livewire Volt + Tailwind CSS v4
- PostgreSQL 16
- Redis 7
- Adapter local de WhatsApp via `whatsapp-web.js`
- PaddleOCR para OCR local
- OpenAI GPT-4o-mini para classificação quando necessário
- Mercado Pago para link Pix
- Docker Compose para ambiente local

## Subida local

```bash
docker compose up -d app nginx postgres redis paddleocr whatsapp-service queue scheduler
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Painel web: [http://localhost:8000](http://localhost:8000)  
Webhook local: `POST /api/webhook/whatsapp`

## Comandos do bot

- `ajuda` ou `menu`: mostra os comandos disponíveis
- `saldo`: envia o resumo do mês atual
- foto de nota fiscal: inicia o fluxo de OCR/classificação
- foto de conta: tenta OCR do valor e cai para confirmação manual se necessário
- `paguei`: marca um fechamento mensal como pago

Quando houver mais de uma cobrança em aberto para o mesmo número, o bot lista as opções e pede o número correspondente antes de confirmar o pagamento.

## Testes

```bash
docker compose exec app php artisan test
```

Para validar só o fluxo do bot:

```bash
docker compose exec app php artisan test tests/Feature/BotRouterTest.php
```

## WhatsApp local

O MVP agora usa um serviço Node.js separado em `whatsapp-service/` para conectar no WhatsApp Web e encaminhar mensagens normalizadas para o Laravel.

1. Copie `whatsapp-service/.env.example` para `whatsapp-service/.env`
2. Suba os containers com `docker compose up -d`
3. Veja o QR Code com `docker compose logs -f whatsapp-service`
4. Escaneie com o WhatsApp que vai operar o bot

Documentação completa: [docs/whatsapp-local-webjs.md](docs/whatsapp-local-webjs.md)
