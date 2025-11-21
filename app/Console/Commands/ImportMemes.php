<?php

namespace App\Console\Commands;

use App\Models\Meme;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportMemes extends Command
{
    protected $signature = 'app:fill-memes {--channels=* : Список каналов, напр. zdes_nedaleko,another} {--id= : Импортировать конкретный пост по ID} {--max= : Ограничить кол-во постов}';
    protected $description = 'Импортировать новые мемы (картинки) из публичных каналов Telegram в локальное хранилище';

    public function handle(): int
    {
        $channels = $this->option('channels');
        if (empty($channels)) {
            $channels = config('services.memes.channels', []);
        }
        if (empty($channels)) {
            $this->warn('Список каналов пуст. Укажите --channels или services.memes.channels.');
            return self::SUCCESS;
        }

        $client = new Client([
            'base_uri' => 'https://t.me/',
            'timeout' => 30,
            'connect_timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; ArcanaBot/1.0)'
            ],
        ]);

        foreach ($channels as $channel) {
            $this->info('Канал: @' . ltrim($channel, '@'));
            $latestId = $this->fetchLatestPostId($client, $channel);
            if ($latestId === null) {
                $this->warn('  Не удалось получить последний пост');
                continue;
            }
            $this->info(" Последний id в канале $latestId");
            $lastInDb = (int) (Meme::where('channel', ltrim($channel, '@'))->max('post_id') ?? 0);
            $this->info(" Последний id в базе $lastInDb");
            $startId = max(1, $lastInDb + 1);
            $importedPosts = 0;
            $importedImages = 0;

            for ($id = $startId; $id <= $latestId; $id++) {
                $count = $this->importImages($client, $channel, $id);
                if ($count > 0) {
                    $importedPosts++;
                    $importedImages += $count;
                }
            }
            $this->info("  Импортировано постов: {$importedPosts}, изображений: {$importedImages}");
        }

        return self::SUCCESS;
    }

    private function fetchLatestPostId(Client $client, string $channel): ?int
    {
        try {
            $resp = $client->get('s/' . ltrim($channel, '@'));
            if ($resp->getStatusCode() !== 200) {
                return null;
            }
            $html = (string) $resp->getBody();
            preg_match_all('#/' . preg_quote(ltrim($channel, '@'), '#') . '/(\d+)#', $html, $m);
            if (empty($m[1])) {
                return null;
            }
            $ids = array_map('intval', $m[1]);
            return empty($ids) ? null : max($ids);
        } catch (\Throwable $e) {
            $this->error($e);
            return null;
        }
    }

    private function importImages(Client $client, string $channel, int $postId): int
    {
        $normalizedChannel = ltrim($channel, '@');
        $url = 's/' . $normalizedChannel . '/' . $postId;

        $this->info("post # $postId");
        try {
            $resp = $client->get($url, ['http_errors' => false, 'allow_redirects' => true]);
            if ($resp->getStatusCode() !== 200) {
                $this->info("response code  {$resp->getStatusCode()}, exit");
                return 0;
            }

            $html = (string) $resp->getBody();
            if (str_contains($html, 'tgme_widget_message_video')
                || str_contains($html, 'tgme_widget_message_document')
                || str_contains($html, 'tgme_widget_message_animation')) {
                return 0;
                $this->info(" no images ");
            }

            $imageUrls = $this->extractImageUrls($html, $normalizedChannel, $postId);
            if (empty($imageUrls)) {
                return 0;
            }

            $imported = 0;
            foreach ($imageUrls as $imageUrl) {
                if ($this->storeImageMetadata($client, $normalizedChannel, $postId, $imageUrl)) {
                    $imported++;
                }
            }

            return $imported;
        } catch (\Throwable $e) {
            $this->error($e);
            return 0;
        }
    }

    private function extractImageUrls(string $html, string $channel, int $postId): array
    {
        $this->info("extract images from $postId");
        $channelQuoted = preg_quote($channel, '#');
        $post = (int) $postId;

        $patternsSection = [
            '#<section[^>]*class=["\']?[^"\']*tgme_widget_message[^"\']*["\']?[^>]*?(?:data-post|data-post-id)=["\']' . $channelQuoted . '/' . $post . '["\'][\s\S]*?</section>#i',
            '#<section[^>]*class=["\']?[^"\']*tgme_widget_message[^"\']*["\']?[^>]*?>[\s\S]*?href=["\']/'. $channelQuoted . '/' . $post . '[^"\']*["\'][\s\S]*?</section>#i',
        ];
        $section = null;
        foreach ($patternsSection as $ps) {
            if (preg_match($ps, $html, $mm)) {
                $section = $mm[0];
                break;
            }
        }

        if ($section !== null) {
            $patternsImg = [
                '#tgme_widget_message_photo[^>]*style=["\'][^"\']*background-image:\s*url\((?:&quot;|\\\"|\')?([^\)"\']+)(?:&quot;|\\\"|\')?\)#i',
                '#background-image:\s*url\((?:&quot;|\\\"|\')?([^\)"\']+)(?:&quot;|\\\"|\')?\)#i',
                '#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i',
                '#data-photo-full=["\']([^"\']+)["\']#i',
            ];
            foreach ($patternsImg as $pi) {
                if (preg_match_all($pi, $section, $m)) {
                    foreach ($m[1] as $match) {
                        $urls[] = $this->normalizeImageUrl($match);
                    }
                }
            }
        }

        $fallbacks = [
            '#tgme_widget_message_photo[^>]*style=["\'][^"\']*background-image:\s*url\((?:&quot;|\\\"|\')?([^\)"\']+)(?:&quot;|\\\"|\')?\)#i',
            '#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i',
        ];
        foreach ($fallbacks as $fb) {
            if (preg_match_all($fb, $html, $m2)) {
                foreach ($m2[1] as $match) {
                    $urls[] = $this->normalizeImageUrl($match);
                }
            }
        }

        $urls = array_values(array_unique(array_filter($urls)));
        //$this->info("extracted urls". implode(', ', $urls));
        return $urls;
    }

    private function guessExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $ext = strtolower((string) $ext);
        return $ext !== '' ? $ext : null;
    }

    private function guessMimeFromUrlOrContent(string $url, string $contents): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            return 'image/jpeg';
        }
        if ($ext === 'png') {
            return 'image/png';
        }
        if ($ext === 'webp') {
            return 'image/webp';
        }
        if (str_starts_with($contents, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($contents, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($contents, "RIFF") && str_contains($contents, "WEBPVP")) {
            return 'image/webp';
        }
        return null;
    }

    private function storeImageMetadata(Client $client, string $channel, int $postId, string $imageUrl): bool
    {
        $this->info("save image $imageUrl");

        $existing = Meme::where('channel', $channel)
            ->where('source_url', $imageUrl)
            ->first();

        if ($existing !== null && $existing->image_extension !== null && $existing->image_mime !== null && $existing->image_data !== null) {
            $this->info(" image $imageUrl exists");
            return false;
        }

        try {
            $response = $client->get($imageUrl, ['http_errors' => false, 'allow_redirects' => true]);
        } catch (\Throwable $e) {
            $this->info("error $e on $imageUrl");
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            $this->info("ret code {$response->getStatusCode()} $imageUrl");
            return false;
        }

        $body = (string) $response->getBody();
        $mimeFromHeader = $this->normalizeMimeHeader($response->getHeaderLine('Content-Type'));
        $mime = $mimeFromHeader ?? $this->guessMimeFromUrlOrContent($imageUrl, $body);

        $extension = $this->guessExtensionFromUrl($imageUrl);
        if ($extension === null && $mime !== null) {
            $extension = $this->extensionFromMime($mime);
        }
        if ($extension === null) {
            $extension = 'jpg'; // default extension
        }

        // Генерируем уникальное имя файла на основе хеша содержимого
        $hash = md5($body);
        $filename = $hash . '.' . $extension;
        $imagePath = 'memes/' . $filename;

        // Сохраняем файл на диск (если еще не существует)
        if (!Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->put($imagePath, $body);
        }

        $data = [
            'channel' => $channel,
            'post_id' => $postId,
            'source_url' => $imageUrl,
            'caption' => null,
            'image_path' => $imagePath,
            'image_extension' => $extension,
            'image_mime' => $mime,
            'image_data' => $body,
        ];

        if ($existing === null) {
            Meme::create($data);
            return true;
        }
        $existing->fill($data);
        $existing->save();
        return true;

    }

    private function normalizeImageUrl(string $raw): string
    {
        $url = html_entity_decode(trim($raw));
        $url = trim($url, "\"' ");
        $url = str_replace('&amp;', '&', $url);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        if (str_starts_with($url, '/')) {
            $url = 'https://t.me' . $url;
        }
        return $url;
    }

    private function normalizeMimeHeader(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }
        $parts = explode(';', $header);
        $mime = trim((string) $parts[0]);
        return $mime !== '' ? strtolower($mime) : null;
    }

    private function extensionFromMime(?string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
    }
}


