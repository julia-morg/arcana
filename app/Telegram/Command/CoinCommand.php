<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;

class CoinCommand implements CommandInterface
{
    public function run(bool $inGroup): Reply
    {
        $result = random_int(0, 1) ? 'Орёл' : 'Решка';

        return new Reply("Результат броска монетки: $result");
    }
}
