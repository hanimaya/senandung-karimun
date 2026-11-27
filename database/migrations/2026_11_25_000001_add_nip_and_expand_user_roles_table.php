<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->change();
            $table->string('nip', 20)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nip']);
            $table->dropColumn('nip');
        });

        // Kembalikan role khusus yang baru ke nilai admin yang didukung skema lama.
        \Illuminate\Support\Facades\DB::table('users')
            ->whereNotIn('role', ['super_admin', 'admin'])
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin'])->default('admin')->change();
        });
    }
};
