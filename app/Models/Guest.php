<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $fillable = [
        'nip',
        'name',
        'agency',
        'employee_status',
        'destination',
        'documents_submitted',
        'service_type',
        'purpose',
        'phone',
        'address',
        'type',
        'recorded_by',
        'entry_time',
        'exit_time',
        'feedback_rating',
        'feedback_comment',
        'feedback_submitted_at',
        'institution_id'
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time'  => 'datetime',
        'documents_submitted' => 'boolean',
        'feedback_submitted_at' => 'datetime',
    ];

    public static array $destinationFields = [
        'Sekretariat BKPSDM',
        'Bidang Pengadaan, Pemberhentian, Informasi dan Fasilitasi ASN',
        'Bidang Penilaian Kinerja Aparatur dan Penghargaan',
        'Bidang Pengembangan Aparatur',
    ];

    public static array $documentStatuses = [
        'Dikirim',
        'Diterima',
        'Diverifikasi',
        'Dikembalikan',
        'Ditolak',
        'Selesai',
    ];

    /**
     * Kode dan label jenis pelayanan BKPSDM Kab. Karimun
     */
    public static array $serviceTypes = [
        'S01' => 'Penyelenggaraan Pelatihan Dasar CPNS',
        'S02' => 'Pengusulan Peserta Diklat',
        'S03' => 'Pengajuan Mutasi ASN Antar Unit Kerja',
        'S04' => 'Pengajuan Mutasi Masuk ASN Lintas Instansi',
        'S05' => 'Pengajuan Mutasi Keluar ASN Lintas Instansi',
        'S06' => 'Pengajuan Cuti Pegawai',
        'S07' => 'Pengajuan Kenaikan Gaji Berkala',
        'S08' => 'Pengajuan Kenaikan Pangkat Reguler/ Kenaikan Pangkat Otomatis',
        'S09' => 'Pengajuan Kenaikan Pangkat Melalui Penyesuaian Ijazah',
        'S10' => 'Permohonan Peninjauan Masa Kerja',
        'S11' => 'Pengajuan Izin Perceraian',
        'S12' => 'Pengajuan Pensiun',
        'S13' => 'Pengajuan Kartu Pegawai',
        'S14' => 'Pengajuan Kartu Suami/Istri',
        'S15' => 'Permintaan Data dan Informasi Kepegawaian',
        'S16' => 'Pengajuan Pencantuman Gelar Akademik',
        'S17' => 'Permohonan Legalisasi Salinan Dokumen Kepegawaian',
        'S18' => 'Pengajuan Pengadaan Pegawai dari Perangkat Daerah',
        'S19' => 'Pengajuan Tugas Belajar',
        'S20' => 'Penanganan Kasus Dugaan Pelanggaran Disiplin Kepegawaian',
        'S21' => 'Pendaftaran CASN',
        'S22' => 'Fasilitasi Pemberian Tanda Kehormatan Satyalancana Karya Satya bagi PNS',
        'S23' => 'DLL',
    ];

    public function getServiceTypeLabelAttribute(): string
    {
        $name = \App\Models\ServiceType::where('institution_id', $this->institution_id)
            ->where('code', $this->service_type)
            ->value('name');

        return $name ?? $this->service_type ?? '-';
    }

    public function scopeActive($query)
    {
        return $query->whereNull('exit_time');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('exit_time');
    }

    public function questionnaireResponses()
    {
        return $this->hasMany(QuestionnaireResponse::class);
    }

    public function documentHistories()
    {
        return $this->hasMany(GuestDocumentHistory::class)->orderBy('created_at')->orderBy('id');
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
}
