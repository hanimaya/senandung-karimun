<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Questionnaire extends Model
{
    protected $fillable = [
        'title',
        'description',
        'questions',
        'is_active',
        'institution_id',
    ];

    protected $casts = [
        'questions'  => 'array',
        'is_active'  => 'boolean',
    ];

    public function responses()
    {
        return $this->hasMany(QuestionnaireResponse::class);
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
            : $query->whereRaw('1 = 0');
    }

    /**
     * Hitung rata-rata skor dari semua responses
     */
    public function averageScore(): float
    {
        return round($this->responses()->whereNotNull('score')->avg('score') ?? 0, 2);
    }
}
