<?php

namespace App\Telegram\Command;


use App\Telegram\CommandInterface;
use App\Telegram\Reply;

class HelpCommand implements CommandInterface
{
    public function run(bool $inGroup): Reply
    {
        return new Reply('help');
    }

}
