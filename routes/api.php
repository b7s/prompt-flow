<?php

use App\Http\Controllers\LinearWebhookController;
use App\Http\Controllers\NightwatchWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(ValidateApiKey::class)->group(function () {
    Route::post('/webhook', [WebhookController::class, '__invoke'])->name('web.webhook');
    Route::post('/webhook/telegram', [TelegramWebhookController::class, '__invoke'])->name('telegram.webhook');
    Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, '__invoke'])->name('whatsapp.webhook');
    Route::post('/webhook/linear', [LinearWebhookController::class, '__invoke'])->name('linear.webhook');
    Route::post('/webhook/nightwatch', [NightwatchWebhookController::class, '__invoke'])->name('nightwatch.webhook');
});
