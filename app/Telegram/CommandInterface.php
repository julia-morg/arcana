<?php

namespace App\Telegram;

interface CommandInterface
{
    public const COMMAND = '';
    public const DESCRIPTION = '';
    public function run(TgMessage $message): Reply;

}
