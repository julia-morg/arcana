<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD10Command extends Command\RollDiceCommand
{
    protected int $sides = 10;
}
