<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    // Путь к каталогу с компилированными Blade-шаблонами
    // ВАЖНО: не используем realpath — если каталога ещё нет, realpath вернёт false
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

    // Дополнительные параметры Blade (совместимы с Laravel 10/11)
    'relative_hash' => false,
    'cache' => env('VIEW_CACHE', true),
    'compiled_extension' => 'php',
];



