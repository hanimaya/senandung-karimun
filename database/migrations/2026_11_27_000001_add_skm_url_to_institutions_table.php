<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('skm_url', 2048)->nullable()->after('code');
        });

        \Illuminate\Support\Facades\DB::table('institutions')->update([
            'skm_url' => 'https://skm.go.id/share/instansi/aa50ae94-8722-4b5e-8b0a-a4e3230b7cf7/1',
        ]);
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('skm_url');
        });
    }
};
