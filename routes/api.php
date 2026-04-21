<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook', [WebhookController::class, 'handle'])
    ->middleware(['zapi.signature', 'throttle:120,1']);
