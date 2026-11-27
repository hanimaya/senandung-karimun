<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questionnaire_responses', function (Blueprint $table) {
            // Token sesi/cookie untuk pembatasan satu kali isi per tamu
            $table->string('respondent_token')->nullable()->after('respondent_name');
            $table->index(['questionnaire_id', 'respondent_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questionnaire_responses', function (Blueprint $table) {
            $table->dropIndex(['questionnaire_id', 'respondent_token']);
            $table->dropColumn('respondent_token');
        });
    }
};
