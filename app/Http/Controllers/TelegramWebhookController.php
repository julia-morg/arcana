<?php

namespace App\Http\Controllers;

use App\Telegram;
use Illuminate\Http\Request;

class TelegramWebhookController
{
    public function index(Request $request)
    {
        file_put_contents('/var/www/html/storage/logs/req.log', print_r($request->all(), true), FILE_APPEND );
        (new Telegram())->handleWebhook();
        return response()->json('OK');
    }
}
