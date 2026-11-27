<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->string('respondent_name')->nullable();
            $table->json('answers'); // {question_id: value}
            $table->decimal('score', 5, 2)->nullable(); // nilai akhir 0-100
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_responses');
    }
};
