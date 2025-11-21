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
    public const bool INLINE_ENABLED = false;
    private const array SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function run(TgMessage $message): Reply
    {
        $meme = Meme::whereNotNull('image_data')
            ->inRandomOrder()
            ->first();

        if (!$meme || !$meme->image_data) {
            return new Reply('К сожалению, мемы пока не загружены. Попробуйте позже.');
        }

        $caption = trim($meme->caption ?? '');

        // Определяем путь к файлу
        $extension = $meme->image_extension ?? 'jpg';
        if (!$meme->image_path) {
            // Генерируем путь если его нет
            $hash = md5($meme->image_data);
            $filename = $hash . '.' . $extension;
            $imagePath = 'memes/' . $filename;
            $meme->image_path = $imagePath;
            $meme->save();
        }

        // Проверяем существует ли файл на диске
        $diskPath = $meme->image_path;
        if (!Storage::disk('public')->exists($diskPath)) {
            // Сохраняем изображение на диск
            Storage::disk('public')->put($diskPath, $meme->image_data);
        }

        // Формируем URL для отдачи через nginx
        $photoUrl = rtrim(config('app.url'), '/') . '/memes/' . basename($diskPath);
        $mime = $meme->image_mime ?? $this->mimeFromExtension($extension);

        return new Reply(
            text: '',
            markup: null,
            photoCaption: $caption,
            photoBytesBase64: null,
            photoMime: $mime,
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

