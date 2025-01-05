<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use OpenAI;

class ArcaneCommand implements CommandInterface
{
    private string $prompt = 'Ты - оракул. Собеседник будет писать тебе за советом. Ты должен не  помогать с техничсекими вопросами, но поддержать и дать собеседнику веру в свои силы. Можно дать совет по тому как успокоиться и прийти в себя если это уместно. Не используй никаких оценочных суждений. Общайся на "вы", но если собеседник обратился на "ты", то  тоже отвечай на "ты"';

    public function run(bool $inGroup): Reply
    {
        $message = !$inGroup
            ? "Напишите ваш вопрос\nРасскажите историю целиком в одном сообщении"
            : "Эта опция работает только в личных сообщениях. Заходите, поговорим";
        return new Reply($message);
    }

    public function handle(string $text)
    {
        $client = OpenAI::client(config('app.openai_key'));

        $response = $client->chat()->create([
            'model' => 'gpt-4o-2024-05-13',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->prompt,
                ],
                [
                    'role' => 'user',
                    'content' => $text,
                ],
            ],
        ]);

        return new Reply($response['choices'][0]['message']['content']);
    }
}
