<?php

namespace App\Telegram;

interface CommandInterface
{
    public const COMMAND = '';
    public const DESCRIPTION = '';
    public const INLINE_ENABLED = false;
    public function run(TgMessage $message): Reply;

}
