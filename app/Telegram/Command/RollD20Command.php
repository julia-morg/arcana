<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD20Command extends Command\RollDiceCommand
{
    protected int $sides = 20;
}
