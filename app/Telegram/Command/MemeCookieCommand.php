<?php

namespace App\Telegram\Command;

use App\Models\Meme;
use App\Telegram\CommandInterface;
use App\Telegram\Reply;
use App\Telegram\TgMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\text;

class MemeCookieCommand implements CommandInterface
{
    public const string COMMAND = 'meme_cookie';
    public const string DESCRIPTION = 'Мем-предсказание';
    public const bool INLINE_ENABLED = true;
    private const array SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function run(TgMessage $message): Reply
    {
        $meme = Meme::whereNotNull('source_url')
            ->where('source_url', '!=', '')
        //    ->where('channel', '=', 'zdes_nedaleko')
            ->inRandomOrder()
            ->first();


        if (!$meme || !$meme->source_url) {
            return new Reply('К сожалению, мемы пока не загружены. Попробуйте позже.');
        }
        $caption = trim($meme->caption ?? '');
        if ($caption === '') {
            $caption = 'Мем специально для @'.$message->username;
        }

        $defaultProxyUrl = rtrim(config('app.url'), '/') . '/tg/cdn4/';
        $proxyUrl = config('app.tg_proxy_base_url', $defaultProxyUrl);

        $cdnPath = ltrim(parse_url($meme->source_url, PHP_URL_PATH) ?? '', '/');
        $photoUrl = rtrim($proxyUrl, '/') . '/' . $cdnPath;


        $extension = $this->resolveExtension($meme->extension, $meme->source_url);
        return new Reply(
            text: '',
            markup: null,
            photoCaption: $caption,
            photoBytesBase64: null,
            photoMime: $mime ?? $this->mimeFromExtension($extension),
            photoUrl: $photoUrl
        );
    }

    private function resolveExtension(?string $mime, string $sourceUrl): string
    {
        if ($mime !== null) {
            $map = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            if (isset($map[$mime])) {
                return $map[$mime];
            }
        }

        $urlPath = parse_url($sourceUrl, PHP_URL_PATH) ?: '';
        $guess = pathinfo($urlPath, PATHINFO_EXTENSION);
        if ($guess !== '') {
            $guess = $this->sanitizeExtension($guess);
            if ($guess !== '') {
                return $guess;
            }
        }

        return 'jpg';
    }

    private function sanitizeExtension(string $ext): string
    {
        $ext = strtolower(trim($ext));

        $map = [
            'jpeg' => 'jpg',
            'jpe' => 'jpg',
            'pjpeg' => 'jpg',
        ];

        $normalized = $map[$ext] ?? $ext;

        if (!in_array($normalized, self::SUPPORTED_EXTENSIONS, true)) {
            return '';
        }

        return $normalized;
    }

    private function mimeFromExtension(string $ext): string
    {
        return match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}

