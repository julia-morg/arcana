<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memes', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('memes', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};


