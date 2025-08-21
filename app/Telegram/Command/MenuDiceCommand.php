<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;

class MenuDiceCommand implements CommandInterface
{
    public function run(TgMessage $message): Reply
    {
        $keyboard = [
            [
                ['text' => 'Бросить d4', 'callback_data' => 'roll_d4'],
                ['text' => 'Бросить d6', 'callback_data' => 'roll_d6'],
            ],
            [
                ['text' => 'Бросить d8', 'callback_data' => 'roll_d8'],
                ['text' => 'Бросить d10', 'callback_data' => 'roll_d10'],
            ],
            [
                ['text' => 'Бросить d12', 'callback_data' => 'roll_d12'],
                ['text' => 'Бросить d20', 'callback_data' => 'roll_d20'],
            ],
            [
                ['text' => 'Назад', 'callback_data' => 'start'],
            ],
        ];

        $markup = json_encode(['inline_keyboard' => $keyboard]);

        return new Reply('Выберите кубик для броска:', $markup);
    }
}
