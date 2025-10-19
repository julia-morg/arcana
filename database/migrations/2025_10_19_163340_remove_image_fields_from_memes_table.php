<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memes', function (Blueprint $table) {
            $table->dropColumn([
                'image_path',
                'image_data',
                'image_mime',
                'image_hash',
                'image_url',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memes', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->longText('image_data')->nullable();
            $table->string('image_mime')->nullable();
            $table->string('image_hash')->nullable();
            $table->string('image_url')->nullable();
        });
    }
};