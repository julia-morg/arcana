<?php

namespace App\Telegram\Command;


use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;

class HelpCommand implements CommandInterface
{
    public function run(TgMessage $message): Reply
    {
        return new Reply('help');
    }

}
