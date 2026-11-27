<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    public const STATUSES = [
        'baru' => 'Baru',
        'diproses' => 'Diproses',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    protected $fillable = [
        'ticket_number',
        'guest_id',
        'institution_id',
        'name',
        'phone',
        'category',
        'subject',
        'message',
        'status',
        'response',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
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
}
