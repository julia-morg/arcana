<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;

abstract class RollDiceCommand implements CommandInterface
{
    protected int $sides = 4;

    public function run(TgMessage $message): Reply
    {
        $result = random_int(1, $this->sides);
        $username = $message->username;
        if(!$message->isInline){
            return new Reply( "$result");
        }

        $prefix = $message->isInline ? '' : (($message->inGroup && $username) ? ('@' . ltrim($username, '@') . ', ') : '');
        return new Reply($prefix . "результат броска d{$this->sides}: $result");
    }
}
