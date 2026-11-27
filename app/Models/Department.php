<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'role_key',
        'is_active',
        'sort_order',
        'institution_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $user->institution_id
            ? $query->where('institution_id', $user->institution_id)
            : $query;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
