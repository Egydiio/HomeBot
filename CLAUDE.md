# HomeBot — CLAUDE.md

## Visão geral

Bot de controle financeiro doméstico via WhatsApp (Z-API). Permite que membros de uma casa registrem notas fiscais e contas, classifiquem despesas como "casa" ou "pessoal", e acompanhem o saldo mensal compartilhado.

## Stack

- PHP 8.4 / Laravel 13 / Livewire Volt
- PostgreSQL 16
- Redis 7
- Docker Compose
- Z-API (WhatsApp)
- PaddleOCR (OCR local — substitui Google Vision)
- OpenAI gpt-4o-mini (fallback de classificação)
- Mercado Pago (Pix)

## Arquitetura do fluxo principal

```
WhatsApp foto
  └─> Queue Job (ProcessReceiptImage)
        └─> ReceiptClassificationPipeline
              ├─ PaddleOcrService        → extrai texto (local, sem custo)
              ├─ OcrProcessorService     → parseia linhas → itens estruturados
              ├─ RuleBasedClassifierService → dicionário no banco (Camada 1)
              └─ OpenAIFallbackClassifierService → apenas itens desconhecidos (Camada 2)
                    └─ itens ambíguos → ConversationState → pergunta 1/2 no WhatsApp
```

## Componentes principais

| Arquivo | Responsabilidade |
|---|---|
| `app/Jobs/ProcessReceiptImage.php` | Job assíncrono de processamento de recibos |
| `app/Services/ReceiptClassificationPipeline.php` | Orquestrador OCR → limpeza → regras → IA → usuário |
| `app/Services/PaddleOcrService.php` | Integração com PaddleOCR local (Docker) |
| `app/Services/OcrProcessorService.php` | Parseia texto cru em itens estruturados |
| `app/Services/RuleBasedClassifierService.php` | Classifica por dicionário; aprende via `learn()` |
| `app/Services/OpenAIFallbackClassifierService.php` | OpenAI apenas para itens desconhecidos |
| `app/Services/Bot/BotRouter.php` | Roteador de estados da conversa WhatsApp |
| `app/Services/Bot/ConversationState.php` | Estado da conversa no Redis (TTL 30 min) |
| `app/Models/ItemCategory.php` | Dicionário persistido no banco |
| `database/seeders/ItemCategorySeeder.php` | ~150 palavras-chave pré-definidas |

## Estados da conversa (Redis)

| Constante | Significado |
|---|---|
| `idle` | Sem conversa ativa |
| `waiting_item_classification` | Aguardando usuário classificar item ambíguo (1/2) |
| `waiting_confirmation` | Aguardando SIM/CORRIGIR após resumo da nota |
| `waiting_manual_value` | OCR falhou — aguardando valor manual |
| `waiting_image_type` | Imagem recebida — aguardando tipo (nota/conta) |

## Classificação em camadas

1. **Dicionário local** (`item_categories` table): acesso instantâneo, sem custo
2. **OpenAI fallback** (`gpt-4o-mini`): envia **apenas os nomes** dos itens desconhecidos, nunca o cupom completo
3. **Confirmação do usuário**: itens ambíguos (confiança < 80%) são enviados ao WhatsApp; resposta é salva como `source = 'user'` e `confidence = 100`

A cada classificação confirmada (IA alta confiança ou usuário), o `RuleBasedClassifierService::learn()` salva no banco para evitar repetição.

## O que foi removido

- **Google Vision API** (`OcrService.php` legado — mantido no repo para referência, não está no fluxo)
- **Llama local** como classificador primário (era caro em memória RAM; OpenAI só para itens sem match)

## Decisões técnicas

- OCR local elimina ~USD 1,50/1000 páginas do Google Vision
- Dicionário cobre ~80% dos itens típicos de supermercado sem custo
- OpenAI processa somente itens desconhecidos (geralmente < 20% do total)
- Redis gerencia estado de conversa com TTL de 30 minutos (já existia)
- `learn()` é chamado automaticamente após IA ou confirmação do usuário — o dicionário cresce com o uso

## Docker

Serviços relevantes no `docker-compose.yml`:

- `paddleocr` — exposta na porta 8866, endpoint `/ocr` aceita upload de imagem
- `queue` — `php artisan queue:work` para processar jobs assíncronos
- `scheduler` — cron do Laravel para fechamento mensal e lembretes de Pix

## Variáveis de ambiente necessárias

```env
PADDLEOCR_ENDPOINT=http://paddleocr:8866
OPENAI_API_KEY=sk-...
ZAPI_INSTANCE=...
ZAPI_TOKEN=...
MERCADOPAGO_ACCESS_TOKEN=...
DB_PASSWORD=...
REDIS_PASSWORD=...
```

## Executar seeders

```bash
php artisan db:seed --class=ItemCategorySeeder
```
