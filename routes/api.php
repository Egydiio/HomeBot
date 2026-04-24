<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whatsapp', [WebhookController::class, 'handle'])
    ->middleware(['whatsapp.webhook', 'throttle:120,1']);

Route::post('/webhook', [WebhookController::class, 'handle'])
    ->middleware(['whatsapp.webhook', 'throttle:120,1']);
