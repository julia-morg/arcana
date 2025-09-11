<?php

namespace App\Telegram\Command;

class DiceCommand extends RollD6Command
{
    public const string COMMAND = 'dice';
    public const string DESCRIPTION = 'Бросить кубик';
    public const INLINE_ENABLED = true;

}
