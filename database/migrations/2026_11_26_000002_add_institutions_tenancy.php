<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'BKPSDM Kabupaten Karimun',
            'code' => 'bkpsdm-karimun',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('role')->constrained('institutions')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('role_key')->constrained('institutions')->nullOnDelete();
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('type')->constrained('institutions')->nullOnDelete();
        });

        DB::table('users')->where('role', '!=', 'super_admin')->update(['institution_id' => $institutionId]);
        DB::table('departments')->update(['institution_id' => $institutionId]);
        DB::table('guests')->update(['institution_id' => $institutionId]);
    }

    public function down(): void
    {
        Schema::table('guests', fn (Blueprint $table) => $table->dropConstrainedForeignId('institution_id'));
        Schema::table('departments', fn (Blueprint $table) => $table->dropConstrainedForeignId('institution_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('institution_id'));
        Schema::dropIfExists('institutions');
    }
};
