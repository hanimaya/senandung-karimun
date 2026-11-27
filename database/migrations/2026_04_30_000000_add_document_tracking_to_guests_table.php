<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('documents_submitted')->default(false)->after('destination');
        });

        Schema::create('guest_document_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->string('recorded_by')->nullable();
            $table->timestamps();
            $table->index(['guest_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_document_histories');

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('documents_submitted');
        });
    }
};