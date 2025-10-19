<?php

namespace App\Http\Controllers;

use App\Telegram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController
{
    public function index(Request $request)
    {
        Log::channel('req')->info('telegram_webhook', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);
        (new Telegram())->handleWebhook();
        return response()->json('OK');
    }
}
