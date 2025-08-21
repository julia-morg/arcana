<?php

use App\Telegram;
use Illuminate\Support\Facades\Artisan;

Artisan::command('app:polling', function () {
    (new Telegram())->handlePolling();
});
