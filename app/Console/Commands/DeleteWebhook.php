<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class DeleteWebhook extends Command
{
    protected $signature = 'app:delete-webhook';

    protected $description = 'Удалить Telegram webhook ';

    public function handle(): int
    {
        $token = config('app.tg_token');
        if (empty($token)) {
            $this->error('Не задан TELEGRAM_TOKEN в окружении.');
            return self::FAILURE;
        }

        try {
            $telegram = new Api($token);
            $telegram->deleteWebhook();
            $this->info('Webhook удален');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка удаления webhook: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}


