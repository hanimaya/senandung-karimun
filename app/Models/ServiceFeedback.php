<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceFeedback extends Model
{
    protected $table = 'service_feedback';

    protected $fillable = [
        'admin_user_id',
        'admin_nip',
        'rating',
        'comment',
    ];
}
