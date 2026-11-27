<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 250);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['institution_id', 'code']);
        });

        $institutionId = DB::table('institutions')->orderBy('id')->value('id');
        if ($institutionId) {
            $legacy = [
                'S01'=>'Penyelenggaraan Pelatihan Dasar CPNS','S02'=>'Pengusulan Peserta Diklat','S03'=>'Pengajuan Mutasi ASN Antar Unit Kerja','S04'=>'Pengajuan Mutasi Masuk ASN Lintas Instansi','S05'=>'Pengajuan Mutasi Keluar ASN Lintas Instansi','S06'=>'Pengajuan Cuti Pegawai','S07'=>'Pengajuan Kenaikan Gaji Berkala','S08'=>'Pengajuan Kenaikan Pangkat Reguler/ Kenaikan Pangkat Otomatis','S09'=>'Pengajuan Kenaikan Pangkat Melalui Penyesuaian Ijazah','S10'=>'Permohonan Peninjauan Masa Kerja','S11'=>'Permohonan Izin Perceraian','S12'=>'Pengajuan Pensiun','S13'=>'Pengajuan Kartu Pegawai','S14'=>'Pengajuan Kartu Suami/Istri','S15'=>'Permintaan Data dan Informasi Kepegawaian','S16'=>'Pengajuan Pencantuman Gelar Akademik','S17'=>'Permohonan Legalisasi Salinan Dokumen Kepegawaian','S18'=>'Pengajuan Pengadaan Pegawai dari Perangkat Daerah','S19'=>'Pengajuan Tugas Belajar','S20'=>'Penanganan Kasus Dugaan Pelanggaran Disiplin Kepegawaian','S21'=>'Pendaftaran CASN','S22'=>'Fasilitasi Pemberian Tanda Kehormatan Satyalancana Karya Satya bagi PNS','S23'=>'DLL',
            ];
            $rows = [];
            $order = 1;
            foreach ($legacy as $code => $name) {
                $rows[] = ['institution_id'=>$institutionId,'code'=>$code,'name'=>$name,'sort_order'=>$order++,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()];
            }
            DB::table('service_types')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
