<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'established_date',
        'country',
        'address',
        'logo_url',
        'logo_public_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
