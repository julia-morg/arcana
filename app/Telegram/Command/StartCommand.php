<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;

class StartCommand implements CommandInterface
{
    public function run(bool $inGroup): Reply
    {
        $keyboard = [
            [
                ['text' => 'Бросить кубик', 'callback_data' => 'dice'],
                ['text' => 'Бросить монетку', 'callback_data' => 'coin'],
            ],
            [
                ['text' => 'Бросить кубик d4-d20', 'callback_data' => 'menu_dice'],
            ],
//            [
//                ['text' => 'Таро', 'callback_data' => 'menu_tarot'],
//                ['text' => 'Прогноз на сегодня', 'callback_data' => 'daily_forecast'],
//            ],
            [
                ['text' => 'Предсказание', 'callback_data' => 'cookie'],
                ['text' => 'Совет оракула', 'callback_data' => 'arcane'],
            ],
        ];

        $markup = json_encode(['inline_keyboard' => $keyboard]);

        return new Reply('Выберите действие:', $markup);
    }
}
