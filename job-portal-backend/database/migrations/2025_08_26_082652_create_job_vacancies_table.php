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
            $table->string('currency', 10)->default('USD'); // new column
            $table->string('location');
            $table->enum('type', ['Full Time', 'Part Time', 'Internship', 'Contract']);
            $table->json('requirements')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('organization_id');
            $table->timestamps();

            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations') // reference organizations
                  ->onDelete('cascade');

            $table->index(['field', 'location', 'type', 'status', 'salary']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_vacancies');
    }
};
