<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique(); // Each user can own one organization profile
            $table->string('name');
            $table->string('email');
            $table->date('established_date');
            $table->string('country')->nullable();
            $table->string('address');
            $table->string('logo_url')->nullable();
            $table->string('logo_public_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('organizations');
    }
};
