<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD6Command extends Command\RollDiceCommand
{
    public const COMMAND = 'roll_d6';
    #public const DESCRIPTION = 'Бросить кубик d6';
    protected int $sides = 6;
}
