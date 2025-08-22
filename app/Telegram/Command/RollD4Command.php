<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD4Command extends Command\RollDiceCommand
{
    public const COMMAND = 'roll_d4';
    public const DESCRIPTION = 'Бросить кубик d4';
    protected int $sides = 4;
}
