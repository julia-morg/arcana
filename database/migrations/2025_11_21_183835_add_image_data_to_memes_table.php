<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Для PostgreSQL используем прямой SQL для создания bytea колонки
        // Schema builder не поддерживает bytea напрямую
        DB::statement('ALTER TABLE memes ADD COLUMN image_data bytea NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memes', function (Blueprint $table) {
            $table->dropColumn('image_data');
        });
    }
};
