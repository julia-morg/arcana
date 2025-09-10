<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;

class StartCommand implements CommandInterface
{
    public const string COMMAND = 'start';
    public const string DESCRIPTION = 'Открыть меню';
    public function run(TgMessage $message): Reply
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
