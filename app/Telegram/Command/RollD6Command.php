<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD6Command extends Command\RollDiceCommand
{
    protected int $sides = 6;
}
