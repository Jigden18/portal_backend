<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'icon'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_job_category');
    }
}
