<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('position');
            $table->string('field')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('location');
            $table->enum('type', ['Full Time', 'Part Time', 'Internship', 'Contract']);
            $table->json('requirements')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('organization_id');
            $table->timestamps();

            // Foreign key constraint to organizations table
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations') // reference organizations
                  ->onDelete('cascade');
            
            // Foreign key to currencies table
            $table->foreign('currency_id')
                  ->references('id')
                  ->on('currencies')
                  ->onDelete('set null');

            // Indexes for faster searches
            $table->index(['field', 'location', 'type', 'status', 'salary']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_vacancies');
    }
};
