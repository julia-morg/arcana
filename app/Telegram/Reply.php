<?php

namespace App\Telegram;

class Reply
{
    public function __construct(
        public ?string $text,
        public ?string $markup = null
    ) {
    }
}
