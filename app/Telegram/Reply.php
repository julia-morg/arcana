<?php

namespace App\Telegram;

class Reply
{
    public function __construct(
        public ?string $text,
        public ?string $markup = null,
        public ?string $photoPath = null,
        public ?string $photoCaption = null,
        public ?string $photoBytesBase64 = null,
        public ?string $photoMime = null,
        public ?string $photoUrl = null,
        public ?string $photoFileId = null
    ) {
    }
}
