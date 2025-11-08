<?php

namespace App\Telegram;

class TgMessage
{
    public function __construct(
        public bool $inGroup,
        public bool $isInline,
        public ?string $username,
        public string $text,
        public ?int $chatId = null,
        public ?int $inMessageId = null
    ) {
    }
}


