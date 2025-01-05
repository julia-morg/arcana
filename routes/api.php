<?php

use Illuminate\Support\Facades\Route;


Route::prefix('webhook')->group(function () {
    Route::post('telegram', [\App\Http\Controllers\TelegramWebhookController::class, 'index']);
});
