<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD8Command extends Command\RollDiceCommand
{
    protected int $sides = 8;
}
