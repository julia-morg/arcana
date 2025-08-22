<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD20Command extends Command\RollDiceCommand
{
    public const COMMAND = 'roll_d20';
    public const DESCRIPTION = 'Бросить кубик d20';
    protected int $sides = 20;
}
