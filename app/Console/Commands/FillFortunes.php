<?php

namespace App\Console\Commands;

use App\Models\Fortune;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use OpenAI;

class FillFortunes extends Command
{
    protected $signature = 'app:fill-fortunes {--count=50 : Сколько предсказаний запросить у ИИ}';

    protected $description = 'Заполнить таблицу fortunes предсказаниями из OpenAI Responses API (уникальные строки)';

    private const INSTRUCTIONS = <<<TXT
Ты пишешь предсказания для печенья на русском языке.
Нужен живой человеческий тон, как у остроумного друга с самоиронией.
Пиши грамотно и естественно, без канцелярита, без "роботных" оборотов и без абсурда.
TXT;

    private const PROMPT = <<<TXT
Сгенерируй {count} уникальных предсказаний в стиле печенья.

Требования к каждой фразе:
- 1 законченное предложение;
- 7-16 слов;
- обращение на "ты";
- ирония, сарказм и мягкая колкость;
- жизненные темы: работа, отношения, деньги, прокрастинация, удача, самооценка;
- разнообразие: не повторяй шаблоны начала и одинаковые конструкции;
- шутки должны быть понятными и естественными для живой русской речи.

Избегай:
- нелепых образов и бессмыслицы;
- морализаторства;
- повторов мыслей с перефразом.
- шуток, упоминаний и намеков про начальников, боссов и руководство.
TXT;

    private const POLISH_INSTRUCTIONS = <<<TXT
Ты редактор коротких юмористических фраз на русском языке.
Твоя задача: править грамматику, управление, сочетаемость и естественность речи.
Сохраняй смысл и тон (ирония/сарказм), не делай фразы "литературно-вылизанными".
TXT;

    private const RESPONSE_SCHEMA_NAME = 'fortune_batch';

    public function handle(): int
    {
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');
        $model = config('services.openai.fortunes_model', config('services.openai.model', 'gpt-4.1'));
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
            $requestPayload = [
                'model' => $model,
                'instructions' => self::INSTRUCTIONS,
                'input' => $prompt,
                'temperature' => 1.0,
                'max_output_tokens' => 8192,
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => self::RESPONSE_SCHEMA_NAME,
                        'strict' => true,
                        'schema' => $this->buildSchema($count),
                    ],
                ],
            ];

            $data = $this->requestResponsesPayload($apiKey, $requestPayload);
            $lines = $this->decodeFortunes($data);
            $normalized = [];
            foreach ($lines as $line) {
                if (!is_string($line)) {
                    continue;
                }
                $text = trim($line);
                if ($text !== '') {
                    $normalized[] = $text;
                }
            }

            if (empty($normalized)) {
                $this->error('Не удалось распарсить ни одной строки.');
                return self::FAILURE;
            }

            $normalized = $this->polishFortunes($apiKey, $model, $normalized);
            if (empty($normalized)) {
                $this->error('Редактор не вернул валидных строк.');
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

    private function polishFortunes(string $apiKey, string $model, array $fortunes): array
    {
        $count = count($fortunes);
        $inputPayload = json_encode(
            ['fortunes' => array_values($fortunes)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $payload = [
            'model' => $model,
            'instructions' => self::POLISH_INSTRUCTIONS,
            'input' => <<<TXT
Исправь список предсказаний. Требования:
- исправляй только ошибки и неестественные обороты;
- оставляй одно предложение на строку;
- 7-16 слов;
- обращение на "ты";
- без повторов и дублей.
- не добавляй шутки, упоминания и намеки про начальников, боссов и руководство.

Входной JSON:
{$inputPayload}
TXT,
            'temperature' => 0.3,
            'max_output_tokens' => 8192,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => self::RESPONSE_SCHEMA_NAME,
                    'strict' => true,
                    'schema' => $this->buildSchema($count),
                ],
            ],
        ];

        $response = $this->requestResponsesPayload($apiKey, $payload);

        return $this->decodeFortunes($response);
    }

    private function requestResponsesPayload(string $apiKey, array $payload): array
    {
        $client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpHeader('Accept', 'application/json')
            ->make();

        try {
            return $client->responses()->create($payload)->toArray();
        } catch (\TypeError $e) {
            if (!str_contains($e->getMessage(), 'CreateResponse::from()')) {
                throw $e;
            }

            // Some transports return text/plain for /responses; fallback keeps command working.
            $httpResponse = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(90)
                ->post('https://api.openai.com/v1/responses', $payload);

            if ($httpResponse->failed()) {
                throw new \RuntimeException(
                    'OpenAI HTTP fallback error: HTTP ' . $httpResponse->status() . ' ' . $httpResponse->body()
                );
            }

            $data = $httpResponse->json();
            if (!is_array($data)) {
                throw new \RuntimeException('OpenAI HTTP fallback вернул не-JSON ответ.');
            }

            return $data;
        }
    }

    private function decodeFortunes(array $payload): array
    {
        $content = $this->extractOutputText($payload);
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Пустой или неподходящий ответ от OpenAI Responses API.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['fortunes']) || !is_array($decoded['fortunes'])) {
            throw new \RuntimeException('Не удалось разобрать JSON со списком предсказаний.');
        }

        return $decoded['fortunes'];
    }

    private function buildSchema(int $count): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['fortunes'],
            'properties' => [
                'fortunes' => [
                    'type' => 'array',
                    'minItems' => $count,
                    'maxItems' => $count,
                    'items' => [
                        'type' => 'string',
                        'minLength' => 20,
                        'maxLength' => 180,
                    ],
                ],
            ],
        ];
    }

    private function extractOutputText(array $payload): ?string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text'])) {
            return $payload['output_text'];
        }

        if (!isset($payload['output']) || !is_array($payload['output'])) {
            return null;
        }

        foreach ($payload['output'] as $item) {
            if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (
                    is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && isset($content['text'])
                    && is_string($content['text'])
                ) {
                    return $content['text'];
                }
            }
        }

        return null;
    }
}
