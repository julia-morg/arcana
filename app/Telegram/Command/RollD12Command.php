<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD12Command extends Command\RollDiceCommand
{
    protected int $sides = 12;
}
