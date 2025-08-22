<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;

class CoinCommand implements CommandInterface
{
    public const COMMAND = 'coin';
    public const DESCRIPTION = 'Бросить монетку';
    public function run(TgMessage $message): Reply
    {
        $result = random_int(0, 1) ? 'Орёл' : 'Решка';
        $username = $message->username;
        $prefix = ($message->inGroup && $username) ? ('@' . ltrim($username, '@') . ', ') : '';
        return new Reply($prefix . "результат броска монетки: $result");
    }
}
