<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 20)->unique();
            $table->string('name', 150);
            $table->string('agency', 150)->nullable();
            $table->string('position', 150)->nullable(); // Jabatan
            $table->string('rank', 100)->nullable();     // Pangkat/Golongan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
