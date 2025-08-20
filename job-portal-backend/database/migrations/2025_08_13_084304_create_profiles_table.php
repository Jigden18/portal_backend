<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('email'); // store from Auth::user()
            $table->date('date_of_birth');
            $table->string('address');
            $table->string('occupation');
            $table->string('photo_url')->nullable();        // Cloudinary URL or initials avatar
            $table->string('photo_public_id')->nullable(); // Cloudinary public_id for deletes
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('profiles');
    }
};
