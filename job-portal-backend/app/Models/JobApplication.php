<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'jobseeker_id',
        'pdf_path',
        'status',
        'message',
        'interview_date',
        'interview_time',
    ];

    /**
     * Each application belongs to a job vacancy.
     */
    public function job()
    {
        return $this->belongsTo(JobVacancy::class, 'job_id');
    }

    /**
     * Each application belongs to a jobseeker (profile).
     */
    public function jobseeker()
    {
        return $this->belongsTo(Profile::class, 'jobseeker_id');
    }
}
