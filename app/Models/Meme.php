<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Meme extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'post_id',
        'source_url',
        'image_path',
        'caption',
        'image_extension',
        'image_mime',
        'image_data',
    ];

    /**
     * Временное хранилище для бинарных данных перед сохранением
     */
    protected ?string $binaryImageData = null;

    /**
     * Переопределяем метод для безопасного сохранения бинарных данных
     */
    public function save(array $options = []): bool
    {
        // Сохраняем бинарные данные во временную переменную
        if (isset($this->attributes['image_data']) && $this->attributes['image_data'] !== null) {
            $this->binaryImageData = $this->attributes['image_data'];
            // Временно убираем из attributes
            unset($this->attributes['image_data']);
        }

        // Сохраняем модель без image_data
        $saved = parent::save($options);

        // После сохранения обновляем image_data через безопасный параметризованный запрос
        if ($saved && $this->binaryImageData !== null) {
            $this->updateBinaryData();
            $this->binaryImageData = null;
        }

        return $saved;
    }

    /**
     * Безопасное обновление бинарных данных через параметризованный запрос
     */
    protected function updateBinaryData(): void
    {
        $pdo = DB::connection()->getPdo();
        
        // Используем параметризованный запрос с PDO::PARAM_LOB для безопасности
        $stmt = $pdo->prepare('UPDATE memes SET image_data = ? WHERE id = ?');
        $stmt->bindValue(1, $this->binaryImageData, \PDO::PARAM_LOB);
        $stmt->bindValue(2, $this->id, \PDO::PARAM_INT);
        $stmt->execute();
        
        // Обновляем attributes для корректной работы модели
        $this->attributes['image_data'] = $this->binaryImageData;
    }

    /**
     * Accessor для получения бинарных данных
     * Bytea данные из PostgreSQL приходят как строка с бинарными данными
     */
    public function getImageDataAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Если это ресурс (stream из PDO), читаем его
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        // Bytea данные уже в правильном формате (бинарная строка)
        return $value;
    }
}


