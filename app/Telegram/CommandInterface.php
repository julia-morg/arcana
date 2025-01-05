<?php

namespace App\Telegram;

interface CommandInterface
{
    public function run(bool $inGroup): Reply;

}
