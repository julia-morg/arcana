<?php

namespace App\Telegram;

class TgMessage
{
    public function __construct(
        public bool $inGroup,
        public ?string $username,
        public string $text
    ) {
    }
}


