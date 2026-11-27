<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $guestsQuery = Guest::forUser(auth()->user());

        $totalGuests   = (clone $guestsQuery)->count();
        $todayGuests   = (clone $guestsQuery)->whereDate('entry_time', today())->count();
        $activeGuests  = (clone $guestsQuery)->whereNull('exit_time')->count();
        $invitedGuests = (clone $guestsQuery)->where('type', 'Undangan')->count();
        $avgRating     = round((clone $guestsQuery)->whereNotNull('feedback_rating')->avg('feedback_rating') ?? 0, 1);

        $recentGuests  = (clone $guestsQuery)->latest('entry_time')->paginate(5)->withQueryString();

        // === Statistik 7 Hari Terakhir (chart) ===
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $last7Days[] = [
                'label' => $date->translatedFormat('D, d M'),
                'count' => (clone $guestsQuery)->whereDate('entry_time', $date)->count(),
            ];
        }

        // === Distribusi Jenis Pelayanan (bulan ini) ===
        $serviceStats = $this->buildServiceStats((clone $guestsQuery)->whereMonth('entry_time', now()->month)
            ->whereYear('entry_time', now()->year)
            ->whereNotNull('service_type')
            ->selectRaw('service_type, COUNT(*) as total')
            ->groupBy('service_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'code'  => $r->service_type,
                'label' => \App\Models\ServiceType::where("code", $r->service_type)->where("institution_id", auth()->user()->institution_id)->value("name") ?? $r->service_type,
                'count' => $r->total,
            ]));

        // === Statistik Kunjungan per Minggu ini ===
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd   = Carbon::now()->endOfWeek();
        $weekGuests = (clone $guestsQuery)->whereBetween('entry_time', [$weekStart, $weekEnd])->count();

        // === Total kunjungan bulan ini ===
        $monthGuests = (clone $guestsQuery)->whereMonth('entry_time', now()->month)
            ->whereYear('entry_time', now()->year)->count();

        return view('dashboard', compact(
            'totalGuests', 'todayGuests', 'activeGuests', 'invitedGuests',
            'avgRating', 'recentGuests', 'last7Days', 'serviceStats',
            'weekGuests', 'monthGuests'
        ));
    }

    private function buildServiceStats($serviceData)
    {
        $sorted = $serviceData->sortByDesc('count')->values()->all();

        if (count($sorted) > 10) {
            $top = array_slice($sorted, 0, 10);
            $other = array_slice($sorted, 10);
            $otherCount = array_sum(array_column($other, 'count'));
            if ($otherCount > 0) {
                $top[] = ['code' => 'LAINNYA', 'label' => 'Lainnya', 'count' => $otherCount];
            }
            return $top;
        }

        return $sorted;
    }
}
