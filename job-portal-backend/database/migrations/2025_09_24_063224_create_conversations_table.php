<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user1_id');
            $table->unsignedBigInteger('user2_id');
            $table->boolean('is_archived_by_user1')->default(false);
            $table->boolean('is_archived_by_user2')->default(false);
            $table->timestamp('user1_last_read_at')->nullable();
            $table->timestamp('user2_last_read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('user1_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user2_id')->references('id')->on('users')->onDelete('cascade');

            // Unique conversation constraint (ensure user1_id < user2_id)
            $table->unique(['user1_id', 'user2_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('conversations');
    }
};
