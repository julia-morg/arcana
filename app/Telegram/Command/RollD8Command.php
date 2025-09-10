<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD8Command extends Command\RollDiceCommand
{
    public const string COMMAND = 'roll_d8';
    #public const DESCRIPTION = 'Бросить кубик d8';
    protected int $sides = 8;
}
