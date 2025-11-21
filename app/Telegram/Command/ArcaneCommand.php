<?php

namespace App\Telegram\Command;

use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;
use App\Models\Message;
use OpenAI;

class ArcaneCommand implements CommandInterface
{
    public const COMMAND = '';
    public const DESCRIPTION = 'Совет оракула';
    private string $prompt = 'Ты - муми-тролль. 
    Мумитроллинг - процесс обратный троллингу. Необходимо говорить человеку по-настоящему приятные, воодушевляющие вещи
    и стараться привести человека в отличное расположение духа.
    Искренность и теплота - важные условия правильного мумитроллинга.
    Собеседник будет писать тебе за советом или с жалобой. 
    Ты должен поддержать и дать собеседнику веру в свои силы. Можно дать совет по тому как успокоиться и прийти в себя если это уместно. 
    Общайся на "ты". Нужно не дать собеседнику раскиснуть. Важно суметь похвалить и поддержать';

    public function run(TgMessage $message): Reply
    {
        if (str_starts_with($message->text, '/')) {
            $message = !$message->inGroup
                ? "Напиши, что тебя беспокоит, я постараюсь помочь"
                : "Напиши мне в личном сообщении, что тебя беспокоит, я постараюсь помочь";
            return new Reply($message);
        } else {
            if ($message->inGroup) {
                return new Reply('');
            }
            return $this->handle($message);
        }
    }

    private function handle(TgMessage $message)
    {
        $client = OpenAI::client(config('app.openai_key'));
        $chatId = $message->chatId;
        $currentText = $message->text;

        $history = [];
        if ($chatId !== null) {
            $query = Message::where('chat_id', $chatId)
                ->where('direction', Message::DIRECTION_IN)
                ->where('is_command', false)
                ->when($message->inMessageId !== null, function ($q) use ($message) {
                    $q->where('external_id', '!=', $message->inMessageId);
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->reverse();

            foreach ($query as $in) {
                $history[] = [
                    'role' => 'user',
                    'content' => $in->message,
                ];
                $reply = Message::where('parent_message_id', $in->id)
                    ->where('direction', Message::DIRECTION_OUT)
                    ->orderBy('id')
                    ->first();
                if ($reply) {
                    $history[] = [
                        'role' => 'assistant',
                        'content' => $reply->message,
                    ];
                }
            }
        }

        $messages = array_merge([
            [
                'role' => 'system',
                'content' => $this->prompt,
            ],
        ], $history, [
            [
                'role' => 'user',
                'content' => $currentText,
            ],
        ]);

        $response = $client->chat()->create([
            'model' => 'gpt-5',
            'messages' => $messages,
        ]);

        return new Reply($response['choices'][0]['message']['content']);
    }
}
