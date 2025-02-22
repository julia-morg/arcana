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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('direction');
            $table->string('status');
            $table->bigInteger('chat_id');
            $table->bigInteger('user_id')->nullable();
            $table->string('username');
            $table->text('message');
            $table->bigInteger('parent_message_id')->nullable();
            $table->bigInteger('external_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
