<?php

namespace App\Services;

// Durante a inclusão deste arquivo, algumas versões do client do Google Cloud
// Vision disparam deprecations via trigger_error() quando o arquivo do vendor
// é carregado. Para evitar que essas mensagens poluam shells interativos (tinker)
// suprimimos temporariamente E_DEPRECATED/E_USER_DEPRECATED/E_USER_ERROR
// até que o processo de bootstrap avance. Restauramos o nível ao final do
// request via shutdown function caso não seja restaurado antes.

// Nota: essa é uma mitigação local e temporária; idealmente o vendor deve ser
// atualizado upstream para não emitir esses warnings.
$__ocr_old_error_level = error_reporting();
$__ocr_suppress_mask = E_DEPRECATED
    | (defined('E_USER_DEPRECATED') ? E_USER_DEPRECATED : 0)
    | (defined('E_USER_ERROR') ? E_USER_ERROR : 0);
error_reporting($__ocr_old_error_level & ~($__ocr_suppress_mask));
register_shutdown_function(function() use ($__ocr_old_error_level) {
    error_reporting($__ocr_old_error_level);
});

use \Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\Image as VisionImage;
use Google\Cloud\Vision\V1\Feature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    private ImageAnnotatorClient $client;

    public function __construct()
    {
        // Algumas versões do client do Google Vision disparam warnings/deprecations
        // internos (vendor) que poluem a saída no runtime (ex: PHP 8.4). Para evitar
        // que essas mensagens quebrem fluxos interativos/CLI, suprimimos
        // temporariamente E_DEPRECATED/E_USER_DEPRECATED durante a construção.
        $oldLevel = error_reporting();
        $suppressMask = E_DEPRECATED
            | (defined('E_USER_DEPRECATED') ? E_USER_DEPRECATED : 0)
            | (defined('E_USER_ERROR') ? E_USER_ERROR : 0);
        try {
            error_reporting($oldLevel & ~$suppressMask);
            $this->client = new ImageAnnotatorClient([
                'credentials' => config('services.google_vision.key_path'),
            ]);
        } finally {
            error_reporting($oldLevel);
        }
    }

    // Método principal — recebe URL da imagem e retorna itens extraídos
    public function extractFromUrl(string $imageUrl): array
    {
        try {
            // Validar que é uma URL HTTPS para prevenir SSRF e path traversal
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || parse_url($imageUrl, PHP_URL_SCHEME) !== 'https') {
                Log::error('OCR: URL inválida ou não-HTTPS rejeitada');
                return $this->emptyResult();
            }

            // Bloquear IPs privados e reservados para prevenir SSRF
            $host = parse_url($imageUrl, PHP_URL_HOST);
            $resolvedIp = gethostbyname($host);
            if ($resolvedIp === $host || filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                Log::error('OCR: host resolve para IP privado ou inválido, rejeitado');
                return $this->emptyResult();
            }

            $imageContent = null;

            // Preparar headers opcionais (ex: uso com Z-API) sem forçar sua existência
            $optionalHeaders = [];
            if ($token = config('services.zapi.client_token')) {
                $optionalHeaders['Client-Token'] = $token;
            }

            $resp = Http::withHeaders($optionalHeaders)->timeout(10)->get($imageUrl);

            if ($resp->successful()) {
                $contentType = $resp->header('Content-Type');

                if ($contentType && preg_match('#^image/#i', $contentType)) {
                    $imageContent = $resp->body();
                } else {
                    Log::warning('OCR: Content-Type não indica imagem', ['content_type' => $contentType]);
                }
            } else {
                Log::warning('OCR: falha ao baixar imagem', ['status' => $resp->status()]);
            }

            if (empty($imageContent)) {
                Log::error('OCR: não foi possível obter o conteúdo da imagem');
                return $this->emptyResult();
            }

            // Evitar enviar HTML ou respostas não-imagem mesmo que o Content-Type seja faltante
            $trimmedStart = substr(ltrim($imageContent), 0, 15);
            if (stripos($trimmedStart, '<!DOCTYPE') === 0 || stripos($trimmedStart, '<html') === 0) {
                Log::error('OCR: conteúdo da resposta parece HTML — abortando');
                return $this->emptyResult();
            }

            return $this->sendToVisionAndParse($imageContent);

        } catch (\Exception $e) {
            Log::error('OCR erro', ['message' => $e->getMessage()]);
            return $this->emptyResult();
        }
    }

    private function sendToVisionAndParse(string $imageContent): array
    {
        $oldLevel = error_reporting();
        $suppressMask = E_DEPRECATED
            | (defined('E_USER_DEPRECATED') ? E_USER_DEPRECATED : 0)
            | (defined('E_USER_ERROR') ? E_USER_ERROR : 0);
        try {
            error_reporting($oldLevel & ~$suppressMask);

            $visionImage = new VisionImage();
            $visionImage->setContent($imageContent);

            $feature = new Feature();
            // TEXT_DETECTION enum value is 5 in the proto definition
            $feature->setType(5);

            $annotateReq = new AnnotateImageRequest();
            $annotateReq->setImage($visionImage);
            $annotateReq->setFeatures([$feature]);

            $batchReq = new BatchAnnotateImagesRequest();
            $batchReq->setRequests([$annotateReq]);

            $response = $this->client->batchAnnotateImages($batchReq);
        } finally {
            error_reporting($oldLevel);
        }

        $responses = $response->getResponses();
        $texts = [];
        if (!empty($responses) && isset($responses[0])) {
            $texts = $responses[0]->getTextAnnotations();
        }

        if (empty($texts)) {
            Log::warning('OCR: nenhum texto encontrado na imagem');
            return $this->emptyResult();
        }

        $rawText = $texts[0]->getDescription();
        Log::info('OCR: texto extraído', ['chars' => mb_strlen($rawText)]);

        return $this->parseWithAI($rawText);
    }

    // Processar arquivo local diretamente — path deve estar dentro do storage
    public function extractFromFile(string $filePath): array
    {
        $realPath = realpath($filePath);
        $storagePath = realpath(storage_path('app'));

        if ($realPath === false || $storagePath === false || !str_starts_with($realPath, $storagePath)) {
            Log::error('OCR: caminho de arquivo fora do storage rejeitado');
            return $this->emptyResult();
        }

        $imageContent = file_get_contents($realPath);

        if ($imageContent === false || empty($imageContent)) {
            Log::error('OCR: não foi possível ler arquivo local');
            return $this->emptyResult();
        }

        $trimmedStart = substr(ltrim($imageContent), 0, 15);
        if (stripos($trimmedStart, '<!DOCTYPE') === 0 || stripos($trimmedStart, '<html') === 0) {
            Log::error('OCR: conteúdo do arquivo parece HTML — abortando');
            return $this->emptyResult();
        }

        return $this->sendToVisionAndParse($imageContent);
    }

    // Usa IA para interpretar o texto cru e extrair itens estruturados
    private function parseWithAI(string $rawText): array
    {
        try {
            // Delegate to the new OcrProcessorService which handles chunking and calling the local Llama-3
            $processor = app(\App\Services\OcrProcessorService::class);
            $result = $processor->processRawText($rawText);

            // Convert processor output to legacy structure { total, items[] }
            $items = [];
            // itens_dividiveis -> category house
            foreach (($result['itens_dividiveis'] ?? []) as $it) {
                $nome = $it['nome'] ?? ($it['name'] ?? '');
                $valor = isset($it['valor_total']) ? $it['valor_total'] : ($it['valor_unitario'] ?? null);
                $valorFloat = is_numeric($valor) ? floatval($valor) : 0.0;
                $items[] = ['name' => $nome, 'value' => $valorFloat, 'category' => 'house'];
            }

            // itens_nao_dividiveis -> category personal
            foreach (($result['itens_nao_dividiveis'] ?? []) as $it) {
                $nome = $it['nome'] ?? ($it['name'] ?? '');
                $valor = isset($it['valor_total']) ? $it['valor_total'] : ($it['valor_unitario'] ?? null);
                $valorFloat = is_numeric($valor) ? floatval($valor) : 0.0;
                $items[] = ['name' => $nome, 'value' => $valorFloat, 'category' => 'personal'];
            }

            $total = null;
            if (!empty($items)) {
                $total = array_sum(array_map(fn($i) => floatval($i['value']), $items));
            }

            return ['total' => $total, 'items' => $items];

        } catch (\Exception $e) {
            Log::error('parseWithAI erro', ['message' => $e->getMessage()]);
            return $this->emptyResult();
        }
    }

    private function buildPrompt(string $rawText): string
    {
        return <<<PROMPT
            Você receberá abaixo o TEXTO EXTRAÍDO de uma nota fiscal de supermercado.

            Sua tarefa: extrair o valor total e a lista de itens e RETORNAR APENAS um único OBJETO JSON válido, sem texto adicional, sem explicações, sem backticks, sem markdown.

            O JSON deve ter exatamente esta estrutura:
            {
              "total": 223.23,
              "items": [
                {"name": "Leite Camponesa 1L", "value": 3.99, "category": "house"}
              ]
            }

            Regras rápidas:
            - "total": número (use ponto como separador decimal)
            - "items": array de objetos {name, value, category}
            - "category": "house" ou "personal" (se dúvida, use "house")
            - Subtraia descontos do item anterior quando aplicável

            RETORNE SOMENTE O JSON ACIMA. Nada mais. TEXTO DA NOTA:
            {$rawText}
            PROMPT;
    }

    // Retorna estrutura vazia — usado como fallback
    private function emptyResult(): array
    {
        return [
            'total' => null,
            'items' => [],
        ];
    }
}
