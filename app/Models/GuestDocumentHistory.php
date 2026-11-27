<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestDocumentHistory extends Model
{
    protected $fillable = [
        'status',
        'note',
        'recorded_by',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}