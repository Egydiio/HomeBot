<?php

namespace Tests\Unit;

use App\Services\OcrService;
use Tests\TestCase;

class OcrServiceTest extends TestCase
{
    public function test_it_defaults_to_document_text_detection(): void
    {
        config(['services.google_vision.feature' => null]);

        $service = app(OcrService::class);

        $this->assertSame(OcrService::VISION_FEATURE_DOCUMENT_TEXT_DETECTION, $service->getGoogleVisionFeatureType());
        $this->assertSame('DOCUMENT_TEXT_DETECTION', $service->getGoogleVisionFeatureName());
    }

    public function test_it_supports_text_detection_when_explicitly_configured(): void
    {
        config(['services.google_vision.feature' => 'text_detection']);

        $service = app(OcrService::class);

        $this->assertSame(OcrService::VISION_FEATURE_TEXT_DETECTION, $service->getGoogleVisionFeatureType());
        $this->assertSame('TEXT_DETECTION', $service->getGoogleVisionFeatureName());
    }
}
