<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // Tambah kolom service_type setelah destination
            $table->string('service_type', 10)->nullable()->after('destination');
        });

        // Migrate data lama: set service_type = 'S01' for existing records
        DB::table('guests')->whereNull('service_type')->update(['service_type' => 'S01']);
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
