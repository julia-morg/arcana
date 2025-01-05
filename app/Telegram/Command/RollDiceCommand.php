<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;

abstract class RollDiceCommand implements CommandInterface
{
    protected int $sides = 4;

    public function run(bool $inGroup): Reply
    {
        $result = random_int(1, $this->sides);
        return new Reply("Результат броска d{$this->sides}: $result");
    }
}
