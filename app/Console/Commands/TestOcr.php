<?php

namespace App\Console\Commands;

use App\Services\OcrProcessorService;
use App\Services\OcrService;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image as VisionImage;
use Illuminate\Console\Command;

class TestOcr extends Command
{
    protected $signature = 'ocr:test {path?} {--debug : Show debug extraction/cleaning output}';
    protected $description = 'Testa o OCR com uma imagem local ou URL';

    public function handle(): int
    {
        $path = $this->argument('path') ?? storage_path('app/teste.jpeg');
        $credentialsPath = config('services.google_vision.key_path');

        $this->info('🔍 Testando OCR...');
        $this->line("📂 Fonte: {$path}");

        if (!is_string($credentialsPath) || trim($credentialsPath) === '') {
            $this->error('❌ GOOGLE_VISION_KEY_PATH nao esta configurado.');
            return self::FAILURE;
        }

        if (!is_file($credentialsPath) || !is_readable($credentialsPath)) {
            $this->error("❌ Arquivo de credenciais do Google Vision indisponivel: {$credentialsPath}");
            return self::FAILURE;
        }

        /** @var OcrService $ocr */
        $ocr = app(OcrService::class);

        $this->line("\n--- 🧠 OCR (Google Vision: {$ocr->getGoogleVisionFeatureName()}) ---");

        try {
            $client = new ImageAnnotatorClient([
                'credentials' => $credentialsPath,
            ]);

            $imageContent = file_get_contents($path);
            if ($imageContent === false || $imageContent === '') {
                $this->error("❌ Nao consegui ler o arquivo: {$path}");
                return self::FAILURE;
            }

            $visionImage = new VisionImage();
            $visionImage->setContent($imageContent);

            $feature = new Feature();
            $feature->setType($ocr->getGoogleVisionFeatureType());

            $annotateReq = new AnnotateImageRequest();
            $annotateReq->setImage($visionImage);
            $annotateReq->setFeatures([$feature]);

            $batchReq = new BatchAnnotateImagesRequest();
            $batchReq->setRequests([$annotateReq]);

            $response = $client->batchAnnotateImages($batchReq);

            $responses = $response->getResponses();
            $texts = [];
            if (!empty($responses) && isset($responses[0])) {
                $texts = $responses[0]->getTextAnnotations();
            }

            if (empty($texts)) {
                $this->error('❌ Nenhum texto encontrado pelo Vision');
                return self::FAILURE;
            }

            $rawText = $texts[0]->getDescription();
        } catch (\Throwable $e) {
            $this->error('❌ Erro no Vision: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line("\n--- 🧹 Preparando linhas para Llama (pre-processamento) ---");

        /** @var OcrProcessorService $processor */
        $processor = app(OcrProcessorService::class);
        $prepared = $processor->prepareStringsForLlama($rawText);

        if ($this->option('debug')) {
            $this->line("\n--- DEBUG: linhas extraidas (candidato a item) ---");
            $extracted = $processor->debugExtractLines($rawText);
            if (empty($extracted)) {
                $this->line('(nenhuma linha candidata encontrada pelo extractor)');
            } else {
                foreach ($extracted as $line) {
                    $this->line($line);
                }
            }

            $this->line("\n--- DEBUG: linhas apos limpeza inicial ---");
            $cleaned = $processor->debugCleanLines($rawText);
            if (empty($cleaned)) {
                $this->line('(nenhuma linha apos limpeza inicial)');
            } else {
                foreach ($cleaned as $line) {
                    $this->line($line);
                }
            }
        }

        $this->info("\n📦 Linhas preparadas (formato: CODIGO - NOME - QTD - VALOR_UNIT - VALOR_TOTAL):");

        if (empty($prepared)) {
            $this->line('(nenhuma linha valida encontrada para envio)');
        } else {
            foreach ($prepared as $preparedLine) {
                $this->line($this->formatPreparedLine($preparedLine));
            }
        }

        $this->line("\n✅ Teste finalizado.");

        return self::SUCCESS;
    }

    private function formatPreparedLine(string $preparedLine): string
    {
        $parts = array_map('trim', explode(' - ', $preparedLine));

        if (count($parts) !== 5) {
            return $preparedLine;
        }

        [$code, $name, $qty, $unit, $total] = $parts;
        $name = mb_strtoupper($name);

        if (preg_match('/kg$/i', $qty)) {
            return sprintf('%s - %s - %s - %s - %s', $code, $name, $qty, $unit, $total);
        }

        $qtyNum = is_numeric($qty) ? (int) $qty : null;

        if ($qtyNum === null) {
            return sprintf('%s - %s - %s - %s - %s', $code, $name, $qty, $unit, $total);
        }

        if ($qtyNum > 1) {
            return sprintf('%s - %s - %sUN - %s - %s', $code, $name, $qtyNum, $unit, $total);
        }

        return sprintf('%s - %s - 1UN - %s - %s', $code, $name, $unit, $total);
    }
}
