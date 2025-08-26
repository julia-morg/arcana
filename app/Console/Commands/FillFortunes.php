<?php

namespace App\Console\Commands;

use App\Models\Fortune;
use Illuminate\Console\Command;

class FillFortunes extends Command
{
    protected $signature = 'app:fill-fortunes {--count=50 : Сколько предсказаний запросить у ИИ}';

    protected $description = 'Заполнить таблицу fortunes предсказаниями из OpenAI (уникальные строки)';

    private const PROMPT = <<<PROMPT
Сгенерируй {count} коротких предсказаний на русском языке.
Требования:
- каждое предсказание — С НОВОЙ СТРОКИ
- без нумерации, без маркеров, без кавычек
- без вводных фраз и комментариев до/после списка
- стиль: остроумно, иронично, колко, по 6–14 слов
- никаких оскорблений и упоминаний попы
- обращайся к собеседнику на ТЫ

Выведи ТОЛЬКО список строк без дополнительных пояснений.
PROMPT;

    public function handle(): int
    {
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');
        $model = config('services.openai.model', 'gpt-4o-mini');
        $count = (int) $this->option('count');
        if ($count < 1 || $count > 300) {
            $this->error('Параметр --count должен быть в диапазоне 1..300');
            return self::FAILURE;
        }

        if (empty($apiKey)) {
            $this->error('Не задан OPENAI_API_KEY в окружении/конфиге.');
            return self::FAILURE;
        }

        try {
            $prompt = str_replace('{count}', (string) $count, self::PROMPT);

            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты креативный помощник. Отвечай только требуемым форматом.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.9,
                'max_tokens' => 2048,
            ]);

            $content = $response->choices[0]->message->content ?? '';
            if (!is_string($content) || trim($content) === '') {
                $this->error('Пустой ответ от OpenAI');
                return self::FAILURE;
            }

            $lines = preg_split("/\r?\n/", $content) ?: [];
            $normalized = [];
            foreach ($lines as $line) {
                $text = trim($line);
                // убираем возможные маркеры/нумерацию
                $text = preg_replace('/^\s*(?:[-*•]|\d+[\.)])\s*/u', '', $text) ?? $text;
                $text = trim($text);
                if ($text !== '') {
                    $normalized[] = $text;
                }
            }

            if (empty($normalized)) {
                $this->error('Не удалось распарсить ни одной строки.');
                return self::FAILURE;
            }

            $inserted = 0;
            $duplicates = 0;
            $seen = [];

            foreach ($normalized as $text) {
                if (isset($seen[$text])) {
                    $duplicates++;
                    continue;
                }
                $seen[$text] = true;

                $exists = Fortune::query()->where('text', $text)->exists();
                if ($exists) {
                    $duplicates++;
                    continue;
                }
                Fortune::create(['text' => $text]);
                $inserted++;
            }

            $this->info('Получено строк: ' . count($normalized));
            $this->info('Добавлено новых: ' . $inserted);
            $this->info('Пропущено (дубликаты): ' . $duplicates);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка при обращении к OpenAI: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}


