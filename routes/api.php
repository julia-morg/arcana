<?php

use Illuminate\Support\Facades\Route;


Route::prefix('webhook')->group(function () {
    Route::post('telegram', [\App\Http\Controllers\TelegramWebhookController::class, 'index']);
});

Route::get('meme/{id}', [\App\Http\Controllers\MemeController::class, 'show']);
