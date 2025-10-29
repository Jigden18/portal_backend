<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable; // Import Notifiable trait

class Profile extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'date_of_birth',
        'address',
        'occupation',
        'photo_url',
        'photo_public_id',
    ];

    /**
     * Each profile belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Each profile has many job bookmarks
     */
    public function bookmarks()
    {
        return $this->belongsToMany(JobVacancy::class, 'job_bookmarks')
                    ->withTimestamps();
    }

    /**
     * Each profile (jobseeker) has many applications
     */
    public function applications()
    {
        return $this->hasMany(JobApplication::class, 'jobseeker_id');
    }
}
