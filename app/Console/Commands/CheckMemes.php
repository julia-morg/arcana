<?php

namespace App\Console\Commands;

use App\Models\Meme;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckMemes extends Command
{
    protected $signature = 'app:check-memes {--limit=50 : Maximum number of memes to check}';
    protected $description = 'Check if memes in database are still available and remove deleted ones';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Checking up to {$limit} memes for availability...");

        $memes = Meme::inRandomOrder()->limit($limit)->get();
        $checked = 0;
        $deleted = 0;

        $client = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);

        foreach ($memes as $meme) {
            $checked++;
            $this->info("Checking meme {$checked}/{$memes->count()}: {$meme->source_url}");

            try {
                $response = $client->get($meme->source_url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    ]
                ]);

                $statusCode = $response->getStatusCode();
                
                // Если пост удален или недоступен
                if ($statusCode === 404 || $statusCode === 403 || $statusCode >= 500) {
                    $this->warn("Meme {$meme->id} is no longer available (HTTP {$statusCode}), removing...");
                    $meme->delete();
                    $deleted++;
                    
                    Log::info("Deleted unavailable meme", [
                        'id' => $meme->id,
                        'url' => $meme->source_url,
                        'status_code' => $statusCode,
                    ]);
                } elseif ($statusCode === 200) {
                    $this->info("Meme {$meme->id} is still available");
                } else {
                    $this->warn("Meme {$meme->id} returned unexpected status: {$statusCode}");
                }

            } catch (\Throwable $e) {
                $this->error("Error checking meme {$meme->id}: " . $e->getMessage());
                
                // Логируем ошибку, но не удаляем мем при сетевых проблемах
                Log::warning("Failed to check meme availability", [
                    'id' => $meme->id,
                    'url' => $meme->source_url,
                    'error' => $e->getMessage(),
                ]);
            }

            // Небольшая пауза между запросами
            usleep(100000); // 0.1 секунды
        }

        $this->info("Check completed. Checked: {$checked}, Deleted: {$deleted}");
        
        Log::info("Meme availability check completed", [
            'checked' => $checked,
            'deleted' => $deleted,
        ]);

        return Command::SUCCESS;
    }
}