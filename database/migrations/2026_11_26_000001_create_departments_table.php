<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('role_key')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $departments = [
            ['Sekretariat BKPSDM', 'sekretariat', 'admin_sekretariat'],
            ['Bidang Pengadaan, Pemberhentian, Informasi dan Fasilitasi ASN', 'pengadaan', 'admin_pengadaan'],
            ['Bidang Penilaian Kinerja Aparatur dan Penghargaan', 'kinerja', 'admin_kinerja'],
            ['Bidang Pengembangan Aparatur', 'kompetensi', 'admin_pengembangan'],
        ];

        foreach ($departments as $index => [$name, $slug, $roleKey]) {
            DB::table('departments')->insert([
                'name' => $name,
                'slug' => $slug,
                'role_key' => $roleKey,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
