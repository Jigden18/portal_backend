<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

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
        return $this->belongsTo(User::class);
    }
}
