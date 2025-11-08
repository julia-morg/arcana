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
            $table->dropUnique('memes_channel_post_id_unique');

            $table->string('image_extension', 16)->nullable()->after('image_path');
            $table->string('image_mime', 64)->nullable()->after('image_extension');

            $table->unique(['channel', 'post_id', 'source_url'], 'memes_channel_post_id_source_url_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memes', function (Blueprint $table) {
            $table->dropUnique('memes_channel_post_id_source_url_unique');

            $table->dropColumn(['image_extension', 'image_mime']);

            $table->unique(['channel', 'post_id'], 'memes_channel_post_id_unique');
        });
    }
};
