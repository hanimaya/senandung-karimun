<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireResponse extends Model
{
    protected $fillable = [
        'questionnaire_id',
        'guest_id',
        'respondent_name',
        'respondent_token',
        'answers',
        'score',
    ];

    protected $casts = [
        'answers' => 'array',
        'score'   => 'float',
    ];

    public function questionnaire()
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
