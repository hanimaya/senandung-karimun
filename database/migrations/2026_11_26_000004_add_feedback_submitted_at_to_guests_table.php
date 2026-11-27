<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->timestamp('feedback_submitted_at')->nullable()->after('feedback_comment');
        });

        DB::table('guests')
            ->whereNotNull('feedback_rating')
            ->update(['feedback_submitted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('feedback_submitted_at');
        });
    }
};
