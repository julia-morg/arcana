<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;

class MemeCookieCommand implements CommandInterface
{
    public const string COMMAND = 'meme_cookie';
    public const string DESCRIPTION = 'Мем-предсказание';
    public const bool INLINE_ENABLED = true;

    public function run(TgMessage $message): Reply
    {
        // Получаем случайный мем из базы данных
        $meme = \App\Models\Meme::whereNotNull('source_url')
            ->where('source_url', '!=', '')
            ->inRandomOrder()
            ->first();
            
        if ($meme && $meme->source_url) {
            // Используем HTML разметку для ссылки
            $text = '<a href="' . htmlspecialchars($meme->source_url) . '">мем специально для тебя</a>';
            return new Reply($text);
        }
        
        return new Reply('К сожалению, мемы пока не загружены. Попробуйте позже.');
    }
}


