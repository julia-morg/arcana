<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;

class CookieCommand implements CommandInterface
{
    private string $filePath = '../resources/fortunes.txt';

    public function run(bool $inGroup): Reply
    {
        $fortunes = $this->loadFortunes();
        if (empty($fortunes)) {
            return new Reply('К сожалению, предсказаний пока нет.');
        }

        $prediction = $fortunes[array_rand($fortunes)];
        return new Reply("Ваше предсказание: $prediction");
    }

    private function loadFortunes(): array
    {
        if (!file_exists($this->filePath) || !is_readable($this->filePath)) {
            return [];
        }

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_filter($lines, fn($line) => !empty(trim($line)));
    }
}
