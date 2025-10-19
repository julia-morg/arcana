<?php

namespace App;

use App\Models\Message;
use App\Telegram\Command\ArcaneCommand;
use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\InlineQuery;
use Telegram\Bot\Objects\ChosenInlineResult;
use Telegram\Bot\Objects\Update;

class Telegram
{
    private Api $api;
    private ?string $botUsername = null;
    private const OFFSET_FILE = 'storage/app/private/tg_offset.txt';
    private const INLINE_RESULT_DIR = 'storage/app/private/inline_results';

    public function __construct()
    {
        $this->api = new Api(config('app.tg_token'));
    }

    public function handleWebhook()
    {
        $update = $this->api->getWebhookUpdate();
        $this->handleUpdate($update);
    }

    public function handleUpdate(Update $update)
    {
        
        if ($update->isType('inline_query')) {
            $this->handleInlineQuery($update->inlineQuery);
            return;
        }

        if ($update->isType('chosen_inline_result')) {
            $this->handleChosenInlineResult($update->chosenInlineResult);
            return;
        }

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
            $chatType = $update['callback_query']['message']['chat']['type'] ?? 'private';
            $messageThreadId = $update['callback_query']['message']['message_thread_id'] ?? null;
        } else {
            $message = $update['message']['text'] ?? '';
            $chatId = $update['message']['chat']['id'];
            $messageId = $update['message']['message_id'];
            $userId = $update['message']['from']['id'];
            $userName = $update['message']['from']['username'];
            $chatType = $update['message']['chat']['type'] ?? 'private';
            $messageThreadId = $update['message']['message_thread_id'] ?? null;

            // Fallback for inline results: message posted via our bot
            $viaBotUsername = $update['message']['via_bot']['username'] ?? null;
            $botUsername = $this->getBotUsername();
            if ($viaBotUsername !== null && $botUsername !== null && ltrim(strtolower($viaBotUsername), '@') === strtolower($botUsername)) {
                $commandName = $this->inferInlineCommandNameFromText($message);
                $inMsg = Message::create([
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'username' => $userName,
                    'direction' => Message::DIRECTION_IN,
                    'status' => Message::STATUS_RECEIVED,
                    'message' => '/' . $commandName,
                    'is_command' => true,
                    'received_at' => now(),
                    'parent_message_id' => null,
                    'external_id' => null,
                ]);

                Message::create([
                    'chat_id' => $chatId,
                    'direction' => Message::DIRECTION_OUT,
                    'status' => Message::STATUS_SENT,
                    'user_id' => null,
                    'username' => 'arcana',
                    'message' => $message,
                    'parent_message_id' => $inMsg->id,
                    'external_id' => $messageId,
                ]);
                return;
            }
        }

        if ($message == '') {
            return;
        }
        $isPrivate = ($chatType === 'private');
        if (!$isPrivate && !isset($update['callback_query']) && !str_starts_with($message, '/')) {
            return;
        }

        $isCommand = str_starts_with($message, '/');

        $inMsg = Message::updateOrCreate(
            ['chat_id' => $chatId, 'external_id' => $messageId],
            [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'username' => $userName,
                'direction' => Message::DIRECTION_IN,
                'status' => Message::STATUS_RECEIVED,
                'message' => $message,
                'is_command' => $isCommand,
                'received_at' => now(),
                'parent_message_id' => null,
                'external_id' => $messageId,
            ]
        );


        $result = $this->runCommand($message, !$isPrivate, $userName, $chatId, $messageId);


        if ($result) {
            $logText = ($result->text ?? '') !== ''
                ? $result->text
                : ((($result->photoCaption ?? '') !== '') ? $result->photoCaption : '[photo]');
            Message::create(
                [
                    'chat_id' => $chatId,
                    'direction' => Message::DIRECTION_OUT,
                    'status' => Message::STATUS_SENT,
                    'user_id' => null,
                    'username' => 'arcana',
                    'message' => $logText,
                    'parent_message_id' => $inMsg->id,
                ]
            );
        }

        if ($result !== null && (($result->text ?? '') !== '')) {
            $this->sendReply($chatId, $result, $messageThreadId ?? null);
        }
    }

    public function runCommand(string $text, bool $inGroup, ?string $userName, ?int $chatId = null, ?int $inMessageId = null): ?Reply
    {
        $commandName = $this->guessCmdName($text);
        $commands = $this->getBotCommands();
        if (!isset($commands[$commandName])) {
            Log::error('Command not found: ' . $commandName);
            return null;
        }
        $className = $commands[$commandName]['class'];
        if (!class_exists($className) || !class_implements($className, CommandInterface::class) || !$this->isInstantiable($className)) {
            Log::error('Invalid command class: ' . $className);
            return null;
        }
        $msg = new TgMessage($inGroup, $userName ?? null, $text, $chatId, $inMessageId);
        return (new $className)->run($msg);
    }

    private function guessCmdName(string $text):string
    {
        if (!str_starts_with($text, '/')) {
            return 'arcane';
        }

        if (str_contains($text, '@')) {
            $text = explode('@', $text)[0];
        }
        $firstToken = explode(' ', trim($text))[0] ?? '';
        $commandName = ltrim($firstToken, '/');
        $commandName = strtolower($commandName);
        return $commandName;
    }

    private function inferInlineCommandNameFromText(string $text): string
    {
        $lower = mb_strtolower($text);
        // Heuristics based on message patterns
        if (str_contains($lower, 'предсказание для')) {
            return 'cookie';
        }
        if (str_contains($lower, 'результат броска монетки')) {
            return 'coin';
        }
        if (preg_match('/результат броска d\d+: /ui', $lower)) {
            return 'dice';
        }
        return 'arcane';
    }

    private function sendReply(string $chatId, Reply $result, ?int $messageThreadId = null)
    {
        if (($result->photoBytesBase64 ?? '') !== '') {
            try {
                $raw = base64_decode($result->photoBytesBase64, true) ?: '';
                if ($raw === '') {
                    throw new \RuntimeException('empty_photo_bytes');
                }
                $filename = 'meme.' . (($result->photoMime === 'image/png') ? 'png' : 'jpg');
                $params = [
                    'chat_id' => $chatId,
                    'photo' => \Telegram\Bot\FileUpload\InputFile::createFromContents($raw, $filename),
                ];
                if (($result->photoCaption ?? '') !== '') {
                    $params['caption'] = $result->photoCaption;
                }
                if ($result->markup !== null) {
                    $params['reply_markup'] = $result->markup;
                }
                if ($messageThreadId !== null) {
                    $params['message_thread_id'] = $messageThreadId;
                }
                $this->api->sendPhoto($params);
                return;
            } catch (\Throwable $e) {
                Log::error('Failed to send photo (bytes)', ['error' => $e->getMessage()]);
                if (($result->text ?? '') === '' && ($result->photoCaption ?? '') !== '') {
                    $result = new Reply($result->photoCaption, $result->markup);
                }
            }
        }

        if (($result->photoUrl ?? '') !== '') {
            try {
                $params = [
                    'chat_id' => $chatId,
                    'photo' => \Telegram\Bot\FileUpload\InputFile::create($result->photoUrl),
                ];
                if (($result->photoCaption ?? '') !== '') {
                    $params['caption'] = $result->photoCaption;
                }
                if ($result->markup !== null) {
                    $params['reply_markup'] = $result->markup;
                }
                if ($messageThreadId !== null) {
                    $params['message_thread_id'] = $messageThreadId;
                }
                $this->api->sendPhoto($params);
                return;
            } catch (\Throwable $e) {
                Log::error('Failed to send photo (url)', ['error' => $e->getMessage(), 'url' => $result->photoUrl]);
                if (($result->text ?? '') === '' && ($result->photoCaption ?? '') !== '') {
                    $result = new Reply($result->photoCaption, $result->markup);
                }
            }
        }

        if ($result->photoPath !== null && $result->photoPath !== '') {
            try {
                if (!is_file($result->photoPath) || !is_readable($result->photoPath)) {
                    Log::error('Photo not found or not readable', ['path' => $result->photoPath]);
                    throw new \RuntimeException('photo_not_found');
                }
                $params = [
                    'chat_id' => $chatId,
                    'photo' => \Telegram\Bot\FileUpload\InputFile::create($result->photoPath),
                ];
                if ($result->photoCaption !== null && $result->photoCaption !== '') {
                    $params['caption'] = $result->photoCaption;
                }
                if ($result->markup !== null) {
                    $params['reply_markup'] = $result->markup;
                }
                if ($messageThreadId !== null) {
                    $params['message_thread_id'] = $messageThreadId;
                }
                $this->api->sendPhoto($params);
                return;
            } catch (\Throwable $e) {
                Log::error('Failed to send photo', [
                    'error' => $e->getMessage(),
                    'path' => $result->photoPath,
                ]);
                // fallback to text reply if available
                if (($result->text ?? '') === '' && ($result->photoCaption ?? '') !== '') {
                    $result = new Reply($result->photoCaption, $result->markup);
                }
            }
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $result->text,
            'parse_mode' => 'HTML',
            'reply_markup' => $result->markup,
        ];
        if ($messageThreadId !== null) {
            $params['message_thread_id'] = $messageThreadId;
        }
        $this->api->sendMessage($params);
    }

    private function handleInlineQuery(InlineQuery $inlineQuery): void
    {
        try {
            $query = trim((string) ($inlineQuery->query ?? ''));
            $username = $inlineQuery->from->username ?? '';
            // Log invoked inline command as IN when user starts typing (optional): only if query non-empty & is command
            $candidate = strtolower(ltrim(explode(' ', $query)[0] ?? '', '/'));
            if ($candidate !== '') {
                // Do not duplicate on repeated typing: use updateOrCreate by (external_id = inlineQuery->id)
                Message::updateOrCreate(
                    [
                        'chat_id' => $inlineQuery->from->id,
                        'external_id' => $inlineQuery->id,
                        'direction' => Message::DIRECTION_IN,
                    ],
                    [
                        'user_id' => $inlineQuery->from->id,
                        'username' => $username,
                        'status' => Message::STATUS_RECEIVED,
                        'message' => '/' . $candidate,
                        'is_command' => true,
                        'received_at' => now(),
                        'parent_message_id' => null,
                    ]
                );
            }

            $results = $this->buildInlineResults($query, $username);
            if (empty($results)) {
                return;
            }

            // Save prepared results mapping to filesystem (no DB writes)
            foreach ($results as $res) {
                $resultId = $res['id'] ?? null;
                $text = $res['input_message_content']['message_text'] ?? null;
                if ($resultId === null || ($text === null || $text === '')) {
                    continue;
                }
                $this->saveInlineResultMapping($resultId, $inlineQuery->from->id, $username, $text);
            }

            $this->api->answerInlineQuery([
                'inline_query_id' => $inlineQuery->id,
                'results' => json_encode($results),
                'is_personal' => true,
                'cache_time' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to answer inline query: ' . $e->getMessage());
        }
    }

    private function handleChosenInlineResult(ChosenInlineResult $chosen): void
    {
        try {
            $resultId = $chosen->resultId;
            $username = $chosen->from->username ?? '';

            // Resolve selected text from filesystem mapping (preferred)
            $mapping = $this->consumeInlineResultMapping($resultId);
            $selectedText = is_array($mapping ?? null) ? ($mapping['text'] ?? null) : null;
            if ($selectedText === null || $selectedText === '') {
                // Fallback: try to rebuild (may be non-deterministic)
                $results = $this->buildInlineResults(trim($chosen->query ?? ''), $username);
                foreach ($results as $res) {
                    if (($res['id'] ?? null) === $resultId) {
                        $selectedText = $res['input_message_content']['message_text'] ?? null;
                        break;
                    }
                }
            }
            if ($selectedText === null || $selectedText === '') {
                Log::warning('Chosen inline result not found for resultId=' . $resultId);
                return;
            }

            // IN: log invoked inline command based on resultId prefix (fallback to query)
            $invokedName = null;
            if (str_contains($resultId, ':')) {
                $invokedName = explode(':', $resultId)[0] ?? null;
            }
            if ($invokedName === null || $invokedName === '') {
                $rawQuery = trim($chosen->query ?? '');
                $invokedName = strtolower(ltrim(explode(' ', $rawQuery)[0] ?? '', '/')) ?: 'arcane';
            }
            $invoked = '/' . $invokedName;
            $inMsg = Message::create([
                'chat_id' => $chosen->from->id,
                'user_id' => $chosen->from->id,
                'username' => $username,
                'direction' => Message::DIRECTION_IN,
                'status' => Message::STATUS_RECEIVED,
                'message' => $invoked,
                'is_command' => true,
                'received_at' => now(),
                'parent_message_id' => null,
                'external_id' => $resultId,
            ]);

            // Универсально: при выборе inline-результата подменяем на реальный ответ команды
            try {
                $reply = $this->runCommand($invoked, true, $username ?? null);
                $inlineMessageId = $chosen->inlineMessageId ?? null;
                if ($inlineMessageId && $reply !== null && ($reply->text ?? '') !== '') {
                    $this->api->editMessageText([
                        'inline_message_id' => $inlineMessageId,
                        'text' => $reply->text,
                        'parse_mode' => 'HTML',
                    ]);
                    $selectedText = $reply->text;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to edit inline message to command response: ' . $e->getMessage());
            }

            // OUT: bot posted the selected text (лог)
            Message::create([
                'chat_id' => $chosen->from->id,
                'direction' => Message::DIRECTION_OUT,
                'status' => Message::STATUS_SENT,
                'user_id' => null,
                'username' => 'arcana',
                'message' => $selectedText,
                'parent_message_id' => $inMsg->id,
                'external_id' => $resultId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to handle chosen_inline_result: ' . $e->getMessage());
        }
    }

    private function buildInlineResults(string $query, ?string $username): array
    {
        $commands = $this->getBotCommands();
        $preferred = [];
        $candidate = strtolower(trim($query));
        $candidate = $candidate !== '' ? ltrim(explode(' ', $candidate)[0] ?? '', '/') : '';
        foreach ($commands as $name => $meta) {
            $className = $meta['class'] ?? null;
            if ($className === null) {
                continue;
            }
            if (!defined($className . '::INLINE_ENABLED') || !($className::INLINE_ENABLED)) {
                continue;
            }
            if ($candidate !== '' && $name !== $candidate) {
                continue;
            }
            $preferred[] = $name;
        }

        $results = [];
        foreach ($preferred as $name) {
            $className = $commands[$name]['class'] ?? null;
            if ($className === null) {
                continue;
            }

            $reply = $this->runCommand('/' . $name, true, $username ?? null);
            if ($reply === null) {
                continue;
            }

            $title = (string) constant($className . '::DESCRIPTION');


            $displayText = ($reply->text ?? '') !== ''
                ? (string) $reply->text
                : ((($reply->photoCaption ?? '') !== '') ? (string) $reply->photoCaption : $title);
            if ($displayText === '' || $displayText === null) {
                $displayText = $title;
            }
            $idSalt = (string) $displayText;
            $results[] = [
                'type' => 'article',
                'id' => $name . ':' . substr(md5($name . '|' . $idSalt), 0, 32),
                'title' => $title,
                'input_message_content' => [
                    'message_text' => (string) $displayText,
                    'parse_mode' => 'HTML',
                ],
            ];
        }
        return $results;
    }

    private function ensureInlineResultDir(): void
    {
        $dir = self::INLINE_RESULT_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    private function saveInlineResultMapping(string $resultId, int $userId, string $username, string $text): void
    {
        $this->ensureInlineResultDir();
        $file = self::INLINE_RESULT_DIR . '/' . $resultId . '.json';
        $payload = json_encode([
            'text' => $text,
            'user_id' => $userId,
            'username' => $username,
            'invoked' => explode(':', $resultId)[0] ?? null,
            'created_at' => time(),
        ], JSON_UNESCAPED_UNICODE);
        @file_put_contents($file, $payload, LOCK_EX);
    }

    private function consumeInlineResultMapping(string $resultId): ?array
    {
        $file = self::INLINE_RESULT_DIR . '/' . $resultId . '.json';
        if (!file_exists($file)) {
            return null;
        }
        $content = @file_get_contents($file);
        @unlink($file);
        $data = json_decode($content ?: 'null', true);
        return is_array($data) ? $data : null;
    }

    /**
     * Возвращает список доступных команд бота в формате
     * [command (без /) => ['class' => FQCN, 'description' => string]]
     */
    public function getBotCommands(): array
    {
        $directory = app_path('Telegram/Command');
        if (!is_dir($directory)) {
            return [];
        }
        $files = glob($directory . '/*Command.php') ?: [];
        $commands = [];
        foreach ($files as $file) {
            $base = basename($file, '.php');
            $classBase = $base;
            if (!str_ends_with($classBase, 'Command')) {
                continue;
            }
            $className = "App\\Telegram\\Command\\{$classBase}";
            if (!class_exists($className)) {
                require_once $file;
            }
            if (!class_exists($className)) {
                continue;
            }
            if (!class_implements($className, CommandInterface::class)) {
                continue;
            }
            $name = (string) (constant($className . '::COMMAND') ?? '');
            $name = strtolower(ltrim($name, '/'));
            if ($name === '') {
                continue;
            }
            $description = (string) (constant($className . '::DESCRIPTION') ?? '');
            $commands[$name] = [
                'class' => $className,
                'description' => $description,
            ];
        }
        ksort($commands);
        return $commands;
    }

    private function getBotUsername(): ?string
    {
        if ($this->botUsername !== null) {
            return $this->botUsername;
        }
        try {
            $me = $this->api->getMe();
            $username = $me['username'] ?? ($me->username ?? null);
            $this->botUsername = $username ? ltrim($username, '@') : null;
        } catch (\Throwable $e) {
            Log::error('Failed to get bot username: ' . $e->getMessage());
            $this->botUsername = null;
        }
        return $this->botUsername;
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


    public function handlePolling()
    {
        $offset = $this->readOffset();
        $updates = $this->api->getUpdates(['offset' => $offset + 1]);

        foreach ($updates as $update) {
            $updateId = $update['update_id'] ?? null;
            $this->handleUpdate($update);
            if ($updateId !== null) {
                $this->saveOffset($updateId);
            }
        }
    }



}
