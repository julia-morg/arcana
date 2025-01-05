<?php

use App\Telegram;
use Illuminate\Support\Facades\Artisan;

Artisan::command('app:telegram', function () {
    (new Telegram())->handlePolling();
});
