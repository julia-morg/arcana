<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD12Command extends Command\RollDiceCommand
{
    public const string COMMAND = 'roll_d12';
    #public const DESCRIPTION = 'Бросить кубик d12';
    protected int $sides = 12;
}
