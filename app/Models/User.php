<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'nip', 'whatsapp', 'feedback_qr_token', 'institution_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ACCESS_ROLES = [
        'super_admin' => 'Super Admin',
        'admin_sekretariat' => 'Admin Sekretariat BKPSDM',
        'admin_pengadaan' => 'Admin Bidang Pengadaan, Pemberhentian, Informasi dan Fasilitasi ASN',
        'admin_kinerja' => 'Bidang Penilaian Kinerja Aparatur dan Penghargaan',
        'admin_pengembangan' => 'Bidang Pengembangan Aparatur',
        'admin_instansi' => 'Admin Instansi',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->nip && empty($user->feedback_qr_token)) {
                $user->feedback_qr_token = Str::random(48);
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isInstitutionAdmin(): bool
    {
        return $this->role === 'admin_instansi';
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public static function availableAccessRoles(): array
    {
        $roles = self::ACCESS_ROLES;

        $departments = Department::active()->whereNotNull('role_key')->orderBy('sort_order');
        $currentUser = auth()->user();
        if ($currentUser && !$currentUser->isSuperAdmin() && $currentUser->institution_id) {
            $departments->where('institution_id', $currentUser->institution_id);
        }

        foreach ($departments->get() as $department) {
            $roles[$department->role_key] = 'Admin ' . $department->name;
        }

        return $roles;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin'
            || array_key_exists($this->role, self::availableAccessRoles());
    }

    public function accessRoleLabel(): string
    {
        return self::availableAccessRoles()[$this->role] ?? 'Admin';
    }
}
