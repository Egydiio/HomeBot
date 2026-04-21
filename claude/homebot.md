# HomeBot — Contexto do Projeto

## O que é o HomeBot

HomeBot é um SaaS brasileiro de gestão financeira doméstica via WhatsApp. O produto resolve um problema real e cotidiano: pessoas que moram juntas (casais, repúblicas, irmãos) precisam dividir contas e compras do dia a dia, mas hoje fazem isso de forma manual — foto de nota fiscal no WhatsApp, conta digitada no grupo, cobrança esquecida.

O HomeBot automatiza todo esse fluxo:
- Usuário manda foto da nota fiscal no WhatsApp
- Bot lê os itens via OCR + IA e classifica o que é da casa vs pessoal
- Sistema mantém saldo corrido entre os membros
- No 5º dia útil do mês, calcula quem deve quanto e envia link de Pix automaticamente
- Se não pagar, manda lembrete no dia seguinte

## Stack tecnológica

| Camada | Tecnologia | Versão |
|--------|------------|--------|
| Backend | Laravel | 13 |
| Frontend | Livewire Volt + Tailwind CSS v4 | latest |
| Banco de dados | PostgreSQL | 16 |
| Cache / Filas / Estado | Redis | 7 |
| WhatsApp | Z-API | cloud |
| OCR | Google Vision API | v1 |
| Classificação de itens | OpenAI GPT-4o-mini | latest |
| Pagamentos | Mercado Pago | v1 |
| Infra local | Docker Compose | latest |

## Arquitetura do projeto

```
app/
├── Console/Commands/
│   └── CloseMonthCommand.php        # Roda no 5º dia útil — fecha o mês e dispara cobranças
│
├── Http/Controllers/
│   └── WebhookController.php        # Único endpoint — recebe TODAS as mensagens da Z-API
│
├── Jobs/
│   ├── ProcessReceiptImage.php      # OCR em background — não trava o bot
│   ├── SendPixCharge.php            # Dispara cobrança Pix no fechamento do mês
│   └── SendPaymentReminder.php      # Lembrete diário para quem não pagou
│
├── Livewire/                        # Painel web — Single File Components (Volt)
│   └── (vazio — views em resources/views/livewire/)
│
├── Models/
│   ├── Group.php                    # Grupo familiar (ex: "Nossa Casa")
│   ├── Member.php                   # Membro do grupo — tem phone (WhatsApp) e pix_key
│   ├── Transaction.php              # Cada compra ou conta registrada
│   ├── TransactionItem.php          # Itens da nota (category: house | personal)
│   ├── Balance.php                  # Saldo corrido entre dois membros no mês
│   └── MonthlyClose.php             # Fechamento mensal — status: pending | charged | paid
│
└── Services/
    ├── Bot/
    │   ├── BotRouter.php            # Cérebro do bot — roteia por estado da conversa
    │   ├── ConversationState.php    # Estado da conversa salvo no Redis (TTL 30min)
    │   └── Handlers/
    │       ├── ReceiptHandler.php   # Foto de nota fiscal → cria Transaction + dispara OCR
    │       ├── BillHandler.php      # Foto de conta (luz/água) → cria Transaction tipo bill
    │       ├── ClassifyHandler.php  # Mostra itens pro usuário confirmar casa vs pessoal
    │       ├── BalanceHandler.php   # Responde "saldo" com resumo do mês
    │       └── HelpHandler.php      # Responde "oi/ajuda/menu" com lista de comandos
    │
    ├── OcrService.php               # Google Vision → texto bruto → OpenAI → JSON estruturado
    ├── PixService.php               # Mercado Pago → link de pagamento Pix
    ├── BalanceService.php           # Calcula e atualiza saldo corrido entre membros
    ├── BusinessDayService.php       # Calcula o 5º dia útil do mês (considera feriados nacionais)
    └── ZApiService.php              # Envia mensagens e imagens pelo WhatsApp via Z-API
```

## Fluxo principal — nota fiscal

```
1. Usuário manda foto no WhatsApp
2. Z-API chama POST /api/webhook
3. WebhookController → BotRouter
4. BotRouter verifica estado no Redis → idle → chama ReceiptHandler
5. ReceiptHandler cria Transaction (status: pending) e dispara ProcessReceiptImage job
6. Bot responde: "Recebi a foto, processando..."
7. Job roda em background:
   a. OcrService.extractFromUrl() → Google Vision extrai texto bruto
   b. OpenAI interpreta texto → retorna JSON com itens e total
   c. Salva TransactionItems no banco
   d. Chama ClassifyHandler
8. ClassifyHandler manda lista de itens pro usuário confirmar
9. Usuário responde SIM → BalanceService.updateBalance() atualiza saldo corrido
10. Usuário responde CORRIGIR → aguarda correção manual
```

## Fluxo de fechamento mensal

```
1. Schedule roda todo dia às 8h → homebot:close-month
2. BusinessDayService verifica se hoje é o 5º dia útil
3. Se sim: busca todos os grupos ativos
4. Para cada grupo → BalanceService.getMemberSummary() calcula saldo do mês anterior
5. Cria MonthlyClose para cada par devedor/credor
6. Dispara SendPixCharge job para cada MonthlyClose
7. PixService gera link de pagamento no Mercado Pago
8. ZApiService envia mensagem com link + fallback chave Pix
9. Schedule roda todo dia às 9h → verifica MonthlyClose com status charged sem paid_at
10. Dispara SendPaymentReminder para quem não pagou
```

## Estados da conversa (Redis)

O BotRouter usa o ConversationState para saber em que passo cada usuário está:

| Estado | Descrição |
|--------|-----------|
| `idle` | Aguardando nova ação |
| `waiting_classification` | OCR processando — aguarda resultado |
| `waiting_confirmation` | Itens listados — aguarda SIM ou CORRIGIR |
| `waiting_manual_value` | OCR falhou — aguarda valor digitado |
| `waiting_image_type` | Imagem recebida sem contexto — aguarda NOTA ou CONTA |

## Models — relacionamentos principais

```
Group
  └── hasMany Members
  └── hasMany Transactions
  └── hasMany Balances
  └── hasMany MonthlyCloses

Member
  └── belongsTo Group
  └── hasMany Transactions
  └── hasMany Balances (debtor_id e creditor_id)

Transaction
  └── belongsTo Group
  └── belongsTo Member (quem pagou)
  └── hasMany TransactionItems
  └── type: receipt | bill
  └── status: pending | processed | confirmed

Balance
  └── debtor_id → Member (quem deve)
  └── creditor_id → Member (quem recebe)
  └── amount → saldo líquido (já considera compensação inversa)
  └── unique por (debtor_id, creditor_id, reference_month)

MonthlyClose
  └── debtor_id → Member
  └── creditor_id → Member
  └── status: pending | charged | paid
  └── charged_at, paid_at → timestamps de rastreamento
```

## Variáveis de ambiente necessárias

```env
# Banco
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=homebot
DB_USERNAME=homebot
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Z-API — WhatsApp
ZAPI_INSTANCE=
ZAPI_TOKEN=
ZAPI_CLIENT_TOKEN=

# Google Vision — OCR
GOOGLE_VISION_KEY_PATH=/var/www/storage/app/google-vision.json

# OpenAI — classificação de itens
OPENAI_API_KEY=

# Mercado Pago — Pix
MERCADOPAGO_ACCESS_TOKEN=

# HomeBot
HOMEBOT_SPLIT_DEFAULT=50
HOMEBOT_CLOSE_DAY=5
```

## Painel web (Livewire Volt)

O painel web é um dashboard de visualização e configuração. As views ficam em `resources/views/livewire/` como Single File Components do Volt — PHP no topo dentro de `new class extends Component` e HTML dentro de uma única `<div>` raiz.

Rotas do painel:
- `/` → Dashboard — métricas e últimas transações
- `/transactions` → Lista de transações do mês
- `/balance` → Saldo atual entre os membros
- `/monthly-report` → Histórico de fechamentos
- `/settings` → Membros e configurações do grupo

O layout base está em `resources/views/layouts/app.blade.php` com sidebar escura, tipografia DM Sans + DM Mono e tema dark com verde (#1fcc8a) como cor de destaque.

## Convenções do projeto

**Sempre usar:**
- `DB::transaction()` em qualquer operação que altere saldo — evita inconsistência
- Jobs para processos pesados (OCR, envio de Pix) — nunca processar na requisição
- `ConversationState` para qualquer fluxo com múltiplos passos no bot
- `$member->phone` como identificador único do usuário no bot
- `reference_month` no formato `Y-m-01` (primeiro dia do mês)

**Nunca fazer:**
- Chamar OcrService diretamente no WebhookController — sempre via Job
- Alterar Balance diretamente — sempre via BalanceService::updateBalance()
- Hardcodar número de telefone ou chave Pix — sempre via Member model
- Processar mensagens de grupos (isGroup: true) ou do próprio bot (fromMe: true) em produção

## O que está implementado (v1.0)

- [x] Docker — PHP 8.4, Nginx, PostgreSQL 16, Redis 7
- [x] Migrations e Models completos
- [x] WebhookController recebendo mensagens da Z-API
- [x] BotRouter com máquina de estados via Redis
- [x] HelpHandler — responde oi/ajuda/menu
- [x] ReceiptHandler — recebe foto de nota e dispara OCR
- [x] BillHandler — recebe foto de conta de serviço
- [x] ClassifyHandler — lista itens e aguarda confirmação
- [x] BalanceHandler — responde "saldo"
- [x] OcrService — Google Vision + OpenAI
- [x] BalanceService — saldo corrido com compensação inversa
- [x] BusinessDayService — calcula 5º dia útil com feriados
- [x] CloseMonthCommand — fecha o mês automaticamente
- [x] PixService — gera link Mercado Pago + fallback chave Pix
- [x] SendPixCharge job — dispara cobrança no fechamento
- [x] SendPaymentReminder job — lembrete diário
- [x] Painel web Livewire Volt + Tailwind CSS v4
- [x] Dashboard com métricas e últimas transações
- [x] Lista de transações do mês
- [x] Saldo atual entre os membros
- [x] Histórico de fechamentos mensais
- [x] Tela de configurações do grupo (membros, chaves Pix)

## O que falta implementar

- [ ] Configurar Google Vision API com credenciais reais
- [ ] Configurar OpenAI API key
- [ ] Configurar Mercado Pago access token
- [ ] Autenticação no painel web
- [ ] Tela de cadastro de grupo e membros pelo painel
- [ ] Confirmação de pagamento pelo bot ("paguei")
- [ ] Suporte a múltiplos grupos por instância
- [ ] Testes automatizados
- [ ] Deploy em produção (Railway ou Render)
