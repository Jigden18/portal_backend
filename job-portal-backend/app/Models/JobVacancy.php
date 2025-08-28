<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobVacancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'position',
        'field',
        'salary',
        'currency',        
        'location',
        'type',
        'requirements',
        'status',
        'organization_id'
    ];

    protected $casts = [
        'requirements' => 'array',
        'salary' => 'decimal:2',
    ];

    /**
     * A job vacancy belongs to an organization
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Infer field of work from position text
     */
    public static function inferField(string $position): ?string
    {
        $map = [
            'Design'       => ['designer', 'ui', 'ux', 'graphic', 'figma'],
            'Programming'  => ['developer', 'engineer', 'programmer', 'backend', 'frontend', 'fullstack'],
            'Finance'      => ['accountant', 'finance', 'auditor', 'analyst', 'bank'],
            'Marketing'    => ['marketing', 'seo', 'content', 'advertising', 'brand'],
            'Music'        => ['musician', 'composer', 'singer', 'dj'],
        ];

        $positionLower = strtolower($position);

        foreach ($map as $field => $keywords) {
            foreach ($keywords as $word) {
                if (str_contains($positionLower, $word)) {
                    return $field;
                }
            }
        }

        return null;
    }
}
