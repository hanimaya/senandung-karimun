<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('ticket_number', 30)->nullable()->unique()->after('id');
        });

        foreach (DB::table('complaints')->orderBy('id')->get(['id', 'created_at']) as $complaint) {
            $date = $complaint->created_at
                ? date('Ymd', strtotime($complaint->created_at))
                : date('Ymd');

            DB::table('complaints')->where('id', $complaint->id)->update([
                'ticket_number' => 'TKT-' . $date . '-' . str_pad((string) $complaint->id, 5, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropColumn('ticket_number');
        });
    }
};
