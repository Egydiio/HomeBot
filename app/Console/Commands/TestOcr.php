<?php

namespace App\Console\Commands;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\Image as VisionImage;
use Google\Cloud\Vision\V1\Feature;
use Illuminate\Console\Command;
use App\Services\OcrService;

class TestOcr extends Command
{
    protected $signature = 'ocr:test {path?} {--debug : Show debug extraction/cleaning output}';
    protected $description = 'Testa o OCR com uma imagem local ou URL';

    public function handle()
    {
        $path = $this->argument('path') ?? storage_path('app/teste.jpeg');

        $this->info("🔍 Testando OCR...");
        $this->line("📂 Fonte: {$path}");

        /** @var OcrService $ocr */
        $ocr = app(OcrService::class);

        // DEBUG: rodar só Vision primeiro
        $this->line("\n--- 🧠 OCR (Google Vision) ---");

        try {
            $client = new ImageAnnotatorClient([
                'credentials' => config('services.google_vision.key_path'),
            ]);

            $imageContent = str_starts_with($path, 'http')
                ? file_get_contents($path)
                : file_get_contents($path);

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

            $response = $client->batchAnnotateImages($batchReq);

            $responses = $response->getResponses();
            $texts = [];
            if (!empty($responses) && isset($responses[0])) {
                $texts = $responses[0]->getTextAnnotations();
            }

            if (empty($texts)) {
                $this->error("❌ Nenhum texto encontrado pelo Vision");
                return;
            }

            $rawText = $texts[0]->getDescription();

        } catch (\Throwable $e) {
            $this->error("❌ Erro no Vision: " . $e->getMessage());
            return;
        }

        // Agora prepara as linhas que iremos enviar para a IA local (Llama)
        $this->line("\n--- 🧹 Preparando linhas para Llama (pré-processamento) ---");

        /** @var \App\Services\OcrProcessorService $processor */
        $processor = app(\App\Services\OcrProcessorService::class);

        $prepared = $processor->prepareStringsForLlama($rawText);

        // If debug flag provided, print intermediate extraction steps
        if ($this->option('debug')) {
            $this->line("\n--- DEBUG: linhas extraídas (candidato a item) ---");
            $extracted = $processor->debugExtractLines($rawText);
            if (empty($extracted)) {
                $this->line('(nenhuma linha candidata encontrada pelo extractor)');
            } else {
                foreach ($extracted as $l) $this->line($l);
            }

            $this->line("\n--- DEBUG: linhas após limpeza inicial ---");
            $cleanedDebug = $processor->debugCleanLines($rawText);
            if (empty($cleanedDebug)) {
                $this->line('(nenhuma linha após limpeza inicial)');
            } else {
                foreach ($cleanedDebug as $l) $this->line($l);
            }
        }

        $this->info("\n📦 Linhas preparadas (formato: CÓDIGO - NOME - QTD - VALOR_UNIT - VALOR_TOTAL):");

        if (empty($prepared)) {
            $this->line("(nenhuma linha válida encontrada para envio)");
        } else {
            foreach ($prepared as $ln) {
                $parts = array_map('trim', explode(' - ', $ln));

                if (count($parts) === 5) {
                    [$code, $name, $qty, $unit, $total] = $parts;

                    // Uppercase the name
                    $name = mb_strtoupper($name);

                    // Weight items keep KG in qty
                    if (preg_match('/kg$/i', $qty)) {
                        $out = sprintf('%s - %s - %s - %s - %s', $code, $name, $qty, $unit, $total);
                    } else {
                        $qtyNum = is_numeric($qty) ? intval($qty) : null;

                        if ($qtyNum === null) {
                            $out = sprintf('%s - %s - %s - %s - %s', $code, $name, $qty, $unit, $total);
                        } elseif ($qtyNum > 1) {
                            $out = sprintf('%s - %s - %sUN - %s - %s', $code, $name, $qtyNum, $unit, $total);
                        } else {
                            // qty == 1
                            $out = sprintf('%s - %s - 1UN - %s - %s', $code, $name, $unit, $total);
                        }
                    }
                } else {
                    $out = $ln; // fallback
                }

                $this->line($out);
            }
        }

        $this->line("\n✅ Teste finalizado.");
    }
}
