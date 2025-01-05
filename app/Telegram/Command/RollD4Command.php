<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD4Command extends Command\RollDiceCommand
{
    protected int $sides = 4;
}
