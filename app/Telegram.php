<?php

namespace App;

use App\Models\Message;
use App\Telegram\Command\ArcaneCommand;
use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class Telegram
{
    private Api $api;
    private const OFFSET_FILE = 'storage/app/private/tg_offset.txt';

    public function __construct()
    {
        $this->api = new Api(config('app.tg_token'));
    }

    public function handleWebhook()
    {
        $update = $this->api->getWebhookUpdate();
        $this->handleUpdate($update);
    }

    public function handlePolling()
    {
        $offset = $this->readOffset();
        $updates = $this->api->getUpdates(['offset' => $offset + 1]);

        $updatesByChat = [];
        foreach ($updates as $update) {
            $chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
            if ($chatId !== null) {
                $updatesByChat[$chatId] = $update;
            }
        }

        // Обработка последних обновлений в каждом чате
        foreach ($updatesByChat as $chatId => $update) {
            var_dump($chatId);
            $updateId = $update['update_id'];
            $this->handleUpdate($update);
            // $this->saveOffset($updateId);
        }
    }

    public function handleUpdate(Update $update)
    {
        if (!isset($update['callback_query']) && !isset($update['message'])) {
            Log::error('not implemented ' . print_r($update, true));
            return;
        }
        if (isset($update['callback_query'])) {
            $message = '/' . $update['callback_query']['data'];
            $chatId = $update['callback_query']['message']['chat']['id'];
            $messageId = $update['callback_query']['id'];
            $userId = $update['callback_query']['from']['id'];
            $userName = $update['callback_query']['from']['username'];
        } else {
            $message = $update['message']['text'] ?? '';
            $chatId = $update['message']['chat']['id'];
            $messageId = $update['message']['message_id'];
            $userId = $update['message']['from']['id'];
            $userName = $update['message']['from']['username'];
        }

        if (str_starts_with($message, '/')) {
            $result = $this->runCommand($message);
        } else {
            $result = new ArcaneCommand()->handle($message);
        }

        $inMsg = Message::updateOrCreate(
            ['chat_id' => $chatId, 'external_id' => $messageId],
            [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'username' => $userName,
                'direction' => Message::DIRECTION_IN,
                'status' => Message::STATUS_RECEIVED,
                'message' => $message,
                'received_at' => now(),
                'parent_message_id' => null,
                'external_id' => $messageId,
            ]
        );

        if ($result) {
            Message::updateOrCreate(
                ['chat_id' => $chatId, 'parent_message_id' => $inMsg->id],
                [
                    'chat_id' => $chatId,
                    'direction' => Message::DIRECTION_OUT,
                    'status' => Message::STATUS_SENT,
                    'user_id' => null,
                    'username' => 'arcana',
                    'message' => $result->text,
                    'parent_message_id' => $inMsg->id,
                ]
            );
        }

        if ($result !== null) {
            $this->sendReply($chatId, $result);
        }
    }

    public function runCommand(string $text): ?Reply
    {
        $inGroup = false;
        if (str_contains($text, '@')) {
            $text = explode('@', $text)[0];
            $inGroup = true;
        }
        $result = null;
        $command = ucfirst(preg_replace('/[^a-zA-Zа-яА-Я0-9]/u', '', $this->toCamelCase($text)));
        $className = "\App\Telegram\Command\\{$command}Command";
        if (class_exists($className)
            && class_implements($className, CommandInterface::class)
            && $this->isInstantiable($className)) {
            $result = (new $className)->run($inGroup);
        } elseif (class_exists($className)) {
            Log::error($className . ' does not implement ' . CommandInterface::class);
        } else {
            Log::error($className . ' not found ');
        }
        return $result;
    }

    function toCamelCase(string $input): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }


    private function sendReply(string $chatId, Reply $result)
    {
        $this->api->sendMessage([
            'chat_id' => $chatId,
            'text' => $result->text,
            'reply_markup' => $result->markup,
        ]);
    }

    function readOffset(): int
    {
        if (!file_exists(self::OFFSET_FILE)) {
            return 0;
        }

        $content = file_get_contents(self::OFFSET_FILE);

        return is_numeric($content) ? (int)$content : 0;
    }

    function saveOffset(int $offset): void
    {
        file_put_contents(self::OFFSET_FILE, $offset, LOCK_EX);
    }

    function isInstantiable(string $className): bool
    {
        return (new ReflectionClass($className))->isInstantiable();
    }

}
