<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('category', 100);
            $table->string('subject', 200);
            $table->text('message');
            $table->enum('status', ['baru', 'diproses', 'selesai', 'ditolak'])->default('baru');
            $table->text('response')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
