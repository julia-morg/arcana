<?php

namespace App\Telegram\Command;

use App\Telegram\Command;

class RollD10Command extends Command\RollDiceCommand
{
    public const COMMAND = 'roll_d10';
    public const DESCRIPTION = 'Бросить кубик d10';
    protected int $sides = 10;
}
