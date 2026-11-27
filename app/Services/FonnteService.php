<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected string $token;
    protected string $baseUrl = 'https://api.fonnte.com/send';

    public function __construct()
    {
        $this->token = config('services.fonnte.token', '');
    }

    /**
     * Kirim pesan WhatsApp berisi link kuesioner kepada tamu
     */
    public function sendFeedbackLink(Guest $guest): bool
    {
        if (empty($this->token)) {
            Log::warning('Fonnte token tidak dikonfigurasi. Pesan WA tidak terkirim.');
            return false;
        }

        // Nomor harus diawali kode negara
        $phone = $this->normalizePhone($guest->phone);
        if (!$phone) {
            Log::warning("Nomor HP tidak valid untuk guest #{$guest->id}: {$guest->phone}");
            return false;
        }

        // Link kuesioner aktif atau link feedback internal
        $questionnaireQuery = \App\Models\Questionnaire::where('is_active', true);
        $questionnaire = $guest->institution_id
            ? (clone $questionnaireQuery)->where('institution_id', $guest->institution_id)->first()
                ?? (clone $questionnaireQuery)->whereNull('institution_id')->first()
            : $questionnaireQuery->whereNull('institution_id')->first();
        $link = $questionnaire
            ? url("/questionnaire/{$questionnaire->id}/respond?guest={$guest->id}")
            : url("/feedback/{$guest->id}");

        $portalLink = route('guests.service-portal', $guest);
        $institutionName = $guest->institution?->name ?: config('app.name', 'SENANDUNG KARIMUN');

        $message = "Assalamu'alaikum, {$guest->name}.\n\n"
            . "Terima kasih telah berkunjung ke *{$institutionName}*. 🙏\n\n"
            . "Silakan buka 1 tautan berikut untuk mengakses seluruh layanan setelah kunjungan:\n"
            . "👉 {$portalLink}\n\n"
            . "Di dalam tautan tersebut tersedia:\n"
            . "1. Penilaian SKM\n"
            . "2. Penilaian petugas pelayanan\n"
            . "3. Alur dan riwayat dokumen\n\n"
            . "_SENANDUNG KARIMUN — Sistem Elektronik Navigasi Administrasi dan Data Pengunjung_";

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post($this->baseUrl, [
                'target'  => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info("WA feedback terkirim ke {$phone} (guest #{$guest->id})");
                return true;
            }

            Log::error("Fonnte gagal: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Fonnte exception: " . $e->getMessage());
            return false;
        }
    }

    /** Kirim NIP dan kata sandi awal akun pengguna melalui WhatsApp. */
    public function sendAccessCredentials(User $user, string $plainPassword, string $status = 'dibuat'): bool
    {
        if (empty($this->token)) {
            Log::warning('Fonnte token tidak dikonfigurasi. Kredensial pengguna tidak terkirim.', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        $phone = $this->normalizePhone($user->whatsapp ?? '');
        if (!$phone) {
            Log::warning('Nomor WhatsApp pengguna tidak valid.', ['user_id' => $user->id]);
            return false;
        }

        $message = "Halo {$user->name}, akses SENANDUNG KARIMUN Anda telah {$status}.\n\n"
            . "NIP: {$user->nip}\n"
            . "Kata sandi " . ($status === 'dibuat' ? 'awal' : 'baru') . ": {$plainPassword}\n\n"
            . "Silakan masuk melalui: " . url('/login') . "\n"
            . "Jaga kerahasiaan kata sandi Anda.";

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post($this->baseUrl, [
                'target' => $phone,
                'message' => $message,
            ]);

            if ($response->successful() && $response->json('status') === true) {
                Log::info('Kredensial akses pengguna dikirim melalui WhatsApp.', [
                    'user_id' => $user->id,
                    'target' => $phone,
                ]);
                return true;
            }

            Log::warning('Fonnte gagal mengirim kredensial akses pengguna.', [
                'user_id' => $user->id,
                'http_status' => $response->status(),
            ]);
            return false;
        } catch (\Throwable $exception) {
            Log::error('Fonnte mengalami exception saat mengirim kredensial akses pengguna.', [
                'user_id' => $user->id,
                'exception_class' => $exception::class,
            ]);
            return false;
        }
    }

    public function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return preg_match('/^62[0-9]{8,13}$/', $phone) ? $phone : null;
    }
}
