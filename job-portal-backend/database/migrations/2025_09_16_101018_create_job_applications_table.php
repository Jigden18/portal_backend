<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('jobseeker_id');

            // Application details
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['pending', 'Accepted', 'Rejected', 'Scheduled for interview'])
                  ->default('pending');
            
            $table->text('message')->nullable(); // for custom notes from organization

            // New interview scheduling fields
            $table->date('interview_date')->nullable();
            $table->time('interview_time')->nullable();

            $table->timestamps();

            // Constraints
            $table->foreign('job_id')
                  ->references('id')
                  ->on('job_vacancies')
                  ->onDelete('cascade');

            $table->foreign('jobseeker_id')
                  ->references('id')
                  ->on('profiles')   // profile.id is jobseeker_id
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_applications');
    }
};
