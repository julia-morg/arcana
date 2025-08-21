<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SetWebhook extends Command
{
    protected $signature = 'app:set-webhook {url}';

    protected $description = 'Установить Telegram webhook на указанный HTTPS URL';

    public function handle(): int
    {
        $url = $this->argument('url');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Укажите корректный URL.');
            return self::FAILURE;
        }
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            $this->error('URL должен начинаться с https.');
            return self::FAILURE;
        }

        $token = config('app.tg_token');
        if (empty($token)) {
            $this->error('Не задан TELEGRAM_TOKEN в окружении.');
            return self::FAILURE;
        }

        try {
            $telegram = new Api($token);
            $webhookUrl = rtrim($url, '/') . '/api/webhook/telegram/';
            $telegram->setWebhook(['url' => $webhookUrl]);
            $this->info('Webhook успешно установлен: ' . $webhookUrl);
            $this->info('Команды не изменены. Запустите: php artisan app:set-commands');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка установки webhook: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}


