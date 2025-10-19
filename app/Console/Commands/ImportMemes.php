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
            'timeout' => 10,
            'connect_timeout' => 5,
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
            $lastInDb = (int) (Meme::where('channel', ltrim($channel, '@'))->max('post_id') ?? 0);
            $startId = max(1, $lastInDb + 1);
            $imported = 0;
            $oneId = $this->option('id');
            if ($oneId !== null && is_numeric($oneId)) {
                $id = (int) $oneId;
                $ok = $this->importIfSingleImage($client, $channel, $id);
                $this->line('  Пост #' . $id . ': ' . ($ok ? 'OK' : 'SKIP'));
                if ($ok) {
                    $imported++;
                }
                $this->info("  Импортировано: {$imported}");
                continue;
            }

            $max = $this->option('max');
            $limit = (is_numeric($max) && (int)$max > 0) ? (int)$max : PHP_INT_MAX;
            for ($id = $startId; $id <= $latestId && $imported < $limit; $id++) {
                if ($this->importIfSingleImage($client, $channel, $id)) {
                    $imported++;
                }
            }
            $this->info("  Импортировано: {$imported}");
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
        } catch (\Throwable) {
            return null;
        }
    }

    private function importIfSingleImage(Client $client, string $channel, int $postId): bool
    {
        $url = 's/' . ltrim($channel, '@') . '/' . $postId;
        try {
            $resp = $client->get($url, ['http_errors' => false, 'allow_redirects' => true]);
            if ($resp->getStatusCode() !== 200) {
                return false;
            }
            $html = (string) $resp->getBody();
            if (!$this->hasExactlyOneImage($html) || str_contains($html, 'tgme_widget_message_video') || str_contains($html, 'tgme_widget_message_document') || str_contains($html, 'tgme_widget_message_animation')) {
                return false;
            }

            // Найти URL картинки строго внутри секции поста
            $imageUrl = $this->extractImageUrl($html, ltrim($channel, '@'), $postId);
            if ($imageUrl === null) {
                return false;
            }

            Meme::updateOrCreate(
                ['channel' => ltrim($channel, '@'), 'post_id' => $postId],
                [
                    'source_url' => 'https://t.me/' . ltrim($channel, '@') . '/' . $postId,
                    'caption' => null,
                ]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasExactlyOneImage(string $html): bool
    {
        // Определяем одиночную фотографию по наличию og:image и отсутствию групп/альбомов/видео/анимаций
        $hasOg = (bool) preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html);
        if (!$hasOg) {
            // запасной вариант: наличие одного блока photo(_wrap)
            $countPhoto = (int) preg_match_all('/tgme_widget_message_photo(?:_wrap)?/iu', $html, $m);
            if ($countPhoto !== 1) {
                return false;
            }
        }
        if (str_contains($html, 'tgme_widget_message_grouped')) {
            return false;
        }
        return true;
    }

    private function extractImageUrl(string $html, string $channel, int $postId): ?string
    {
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
            ];
            foreach ($patternsImg as $pi) {
                if (preg_match($pi, $section, $m)) {
                    return $this->normalizeImageUrl($m[1]);
                }
            }
        }

        $fallbacks = [
            '#tgme_widget_message_photo[^>]*style=["\'][^"\']*background-image:\s*url\((?:&quot;|\\\"|\')?([^\)"\']+)(?:&quot;|\\\"|\')?\)#i',
            '#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i',
        ];
        foreach ($fallbacks as $fb) {
            if (preg_match($fb, $html, $m2)) {
                return $this->normalizeImageUrl($m2[1]);
            }
        }

        return null;
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
}


