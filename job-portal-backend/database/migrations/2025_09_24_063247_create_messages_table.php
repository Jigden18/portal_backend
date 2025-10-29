<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('message')->nullable();
            $table->enum('type', ['user_message', 'status_update', 'system', 'video_call_start', 'video_call_end'])->default('user_message');
            $table->json('payload')->nullable(); // For attachments or structured data
            $table->timestamp('read_at')->nullable();
            $table->boolean('deleted_by_user1')->default(false);
            $table->boolean('deleted_by_user2')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('messages');
    }
};
