<?php

namespace App\Console\Commands;

use App\Telegram;
use Illuminate\Console\Command;

class Test extends Command
{
    protected $signature = 'app:test';

    protected $description = 'Command description';

    public function handle()
    {
        (new Telegram())->handleWebhook();
    }
}
