<?php

namespace App\Telegram\Command;

use App\Models\Fortune;
use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;

class CookieCommand implements CommandInterface
{
    public const string COMMAND = 'cookie';
    public const string DESCRIPTION = 'Получить предсказание';

    public function run(TgMessage $message): Reply
    {
        $fortune = Fortune::inRandomOrder()->first();

        if (!$fortune) {
            return new Reply('К сожалению, предсказаний пока нет.');
        }

        $prediction = $fortune->text;
        if ($message->inGroup) {
            $username = $message->username;
            $mention = $username ? '@' . ltrim($username, '@') : 'вас';
            return new Reply("Предсказание для $mention:\n$prediction");
        }
        return new Reply("$prediction");
    }
}
