<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $fillable = ['institution_id', 'code', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, User $user)
    {
        return $user->isSuperAdmin()
            ? $query
            : $query->where('institution_id', $user->institution_id);
    }
}
