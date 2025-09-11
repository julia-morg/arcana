<?php

namespace App\Telegram;

class TgMessage
{
    public function __construct(
        public bool $inGroup,
        public ?string $username,
        public string $text,
        public ?int $chatId = null,
        public ?int $inMessageId = null
    ) {
    }
}


