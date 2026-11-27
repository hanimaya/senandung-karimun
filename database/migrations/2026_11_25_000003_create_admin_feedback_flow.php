<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('feedback_qr_token', 64)->nullable()->unique()->after('whatsapp');
        });

        DB::table('users')
            ->whereNotNull('nip')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update([
                    'feedback_qr_token' => Str::random(48),
                ]);
            });

        Schema::create('service_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_nip', 20)->index();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_feedback');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['feedback_qr_token']);
            $table->dropColumn('feedback_qr_token');
        });
    }
};
