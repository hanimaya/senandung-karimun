<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function weekly(Request $request)
    {
        $this->ensureInstitutionScope();
        $week  = $request->integer('week', now()->weekOfYear);
        $year  = $request->integer('year', now()->year);

        $start = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $end   = $start->copy()->endOfWeek();

        $guestQuery = Guest::forUser(auth()->user())->whereBetween('entry_time', [$start, $end]);
        $allGuests = (clone $guestQuery)->orderBy('entry_time')->get();
        $guests = (clone $guestQuery)->orderBy('entry_time')->paginate(5)->withQueryString();

        $stats = $this->buildStats($allGuests);

        $label   = "Minggu ke-{$week}, {$year}";
        $period  = 'weekly';
        $prevWeek = $week > 1 ? $week - 1 : 52;
        $prevYear = $week > 1 ? $year : $year - 1;
        $nextWeek = $week < 52 ? $week + 1 : 1;
        $nextYear = $week < 52 ? $year : $year + 1;

        $serviceChart = $this->buildServiceChart($allGuests);

        return view('reports.index', compact(
            'guests', 'stats', 'label', 'period',
            'week', 'year', 'prevWeek', 'prevYear', 'nextWeek', 'nextYear',
            'start', 'end', 'serviceChart'
        ));
    }

    public function monthly(Request $request)
    {
        $this->ensureInstitutionScope();
        $month = $request->integer('month', now()->month);
        $year  = $request->integer('year', now()->year);

        $start = Carbon::create($year, $month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $guestQuery = Guest::forUser(auth()->user())->whereBetween('entry_time', [$start, $end]);
        $allGuests = (clone $guestQuery)->orderBy('entry_time')->get();
        $guests = (clone $guestQuery)->orderBy('entry_time')->paginate(5)->withQueryString();

        $stats = $this->buildStats($allGuests);

        $label      = Carbon::create($year, $month)->translatedFormat('F Y');
        $period     = 'monthly';
        $prevMonth  = $month > 1 ? $month - 1 : 12;
        $prevMYear  = $month > 1 ? $year : $year - 1;
        $nextMonth  = $month < 12 ? $month + 1 : 1;
        $nextMYear  = $month < 12 ? $year : $year + 1;

        $serviceChart = $this->buildServiceChart($allGuests);

        return view('reports.index', compact(
            'guests', 'stats', 'label', 'period',
            'month', 'year', 'prevMonth', 'prevMYear', 'nextMonth', 'nextMYear',
            'start', 'end', 'serviceChart'
        ));
    }

    public function yearly(Request $request)
    {
        $this->ensureInstitutionScope();
        $year  = $request->integer('year', now()->year);

        $start = Carbon::create($year)->startOfYear();
        $end   = $start->copy()->endOfYear();

        $guestQuery = Guest::forUser(auth()->user())->whereBetween('entry_time', [$start, $end]);
        $allGuests = (clone $guestQuery)->orderBy('entry_time')->get();
        $guests = (clone $guestQuery)->orderBy('entry_time')->paginate(5)->withQueryString();

        $stats = $this->buildStats($allGuests);

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month' => Carbon::create($year, $m)->format('M'),
                'count' => $allGuests->filter(fn($g) => $g->entry_time->month === $m)->count(),
            ];
        }

        $label  = "Tahun {$year}";
        $period = 'yearly';

        $serviceChart = $this->buildServiceChart($allGuests);

        return view('reports.index', compact(
            'guests', 'stats', 'label', 'period',
            'year', 'monthly', 'start', 'end', 'serviceChart'
        ));
    }

    public function custom(Request $request)
    {
        $this->ensureInstitutionScope();
        $request->validate([
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'service_type' => 'nullable|string|max:10',
        ]);

        $dateFrom    = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo      = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : Carbon::now()->endOfDay();
        $serviceType = $request->input('service_type');

        $query = Guest::forUser(auth()->user())->whereBetween('entry_time', [$dateFrom, $dateTo])->orderBy('entry_time');

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }

        $allGuests = (clone $query)->get();
        $guests = (clone $query)->paginate(5)->withQueryString();
        $stats  = $this->buildStats($allGuests);

        $label        = $dateFrom->format('d M Y') . ' — ' . $dateTo->format('d M Y');
        $period       = 'custom';
        $serviceChart = $this->buildServiceChart($allGuests);
        $serviceTypes = (\App\Models\ServiceType::forUser(auth()->user())->active()->orderBy("sort_order")->pluck("name", "code")->all());

        $start = $dateFrom;
        $end   = $dateTo;

        return view('reports.index', compact(
            'guests', 'stats', 'label', 'period',
            'start', 'end', 'serviceChart', 'serviceTypes',
            'dateFrom', 'dateTo', 'serviceType'
        ));
    }

    public function exportExcel(Request $request)
    {
        $this->ensureInstitutionScope();
        $request->validate([
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'service_type' => 'nullable|string|max:10',
            'period'       => 'nullable|string',
        ]);

        $period      = $request->input('period', 'custom');
        $serviceType = $request->input('service_type');

        // Tentukan rentang tanggal berdasarkan period
        switch ($period) {
            case 'weekly':
                $week  = $request->integer('week', now()->weekOfYear);
                $year  = $request->integer('year', now()->year);
                $start = Carbon::now()->setISODate($year, $week)->startOfWeek();
                $end   = $start->copy()->endOfWeek();
                $label = "Minggu ke-{$week}, {$year}";
                break;
            case 'monthly':
                $month = $request->integer('month', now()->month);
                $year  = $request->integer('year', now()->year);
                $start = Carbon::create($year, $month)->startOfMonth();
                $end   = $start->copy()->endOfMonth();
                $label = Carbon::create($year, $month)->translatedFormat('F Y');
                break;
            case 'yearly':
                $year  = $request->integer('year', now()->year);
                $start = Carbon::create($year)->startOfYear();
                $end   = $start->copy()->endOfYear();
                $label = "Tahun {$year}";
                break;
            default: // custom
                $start = $request->filled('date_from')
                    ? Carbon::parse($request->date_from)->startOfDay()
                    : Carbon::now()->startOfMonth();
                $end   = $request->filled('date_to')
                    ? Carbon::parse($request->date_to)->endOfDay()
                    : Carbon::now()->endOfDay();
                $label = $start->format('d-M-Y') . ' sd ' . $end->format('d-M-Y');
        }

        $query = Guest::forUser(auth()->user())->whereBetween('entry_time', [$start, $end])->orderBy('entry_time');

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }

        $guests = $query->get();

        // Build CSV
        $filename = 'Data_Tamu_BKPSDM_' . str_replace([' ', ',', '/'], '_', $label) . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        ];

        $callback = function () use ($guests, $label) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM agar Excel otomatis decode karakter Indonesia
            fputs($handle, "\xEF\xBB\xBF");

            // Baris judul
            fputcsv($handle, ['BKPSDM KABUPATEN KARIMUN — DATA TAMU'], ';');
            fputcsv($handle, ["Periode: {$label}"], ';');
            fputcsv($handle, ["Diekspor: " . now()->timezone('Asia/Jakarta')->format('d F Y H:i') . ' WIB'], ';');
            fputcsv($handle, [], ';');

            // Header kolom
            fputcsv($handle, [
                'No', 'Nama', 'NIP', 'Status Pegawai', 'Instansi',
                'Jenis Tamu', 'Kode Pelayanan', 'Jenis Pelayanan',
                'Tujuan', 'Keperluan', 'Telepon', 'Alamat',
                'Waktu Masuk', 'Waktu Keluar', 'Dicatat Oleh',
            ], ';');

            foreach ($guests as $i => $g) {
                fputcsv($handle, [
                    $i + 1,
                    $g->name,
                    $g->nip ?? '-',
                    $g->employee_status,
                    $g->agency ?? '-',
                    $g->type,
                    $g->service_type ?? '-',
                    $g->service_type_label,
                    $g->destination,
                    $g->purpose ?? '-',
                    $g->phone,
                    $g->address,
                    $g->entry_time->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
                    $g->exit_time ? $g->exit_time->timezone('Asia/Jakarta')->format('d/m/Y H:i') : 'Belum Keluar',
                    $g->recorded_by ?? '-',
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function ensureInstitutionScope(): void
    {
        $user = auth()->user();
        abort_unless($user->isSuperAdmin() || ($user->isInstitutionAdmin() && $user->institution_id), 403);
    }

    private function buildStats($guests): array
    {
        return [
            'total'      => $guests->count(),
            'asn'        => $guests->where('employee_status', 'ASN')->count(),
            'non_asn'    => $guests->where('employee_status', 'Non ASN')->count(),
            'invited'    => $guests->where('type', 'Undangan')->count(),
            'completed'  => $guests->whereNotNull('exit_time')->count(),
            'avg_rating' => round($guests->whereNotNull('feedback_rating')->avg('feedback_rating') ?? 0, 1),
        ];
    }

    private function buildServiceChart($guests): array
    {
        $serviceTypes = (\App\Models\ServiceType::forUser(auth()->user())->active()->orderBy("sort_order")->pluck("name", "code")->all());
        $chart = [];

        foreach ($serviceTypes as $code => $label) {
            $count = $guests->where('service_type', $code)->count();
            if ($count > 0) {
                $chart[] = ['code' => $code, 'label' => $label, 'count' => $count];
            }
        }

        usort($chart, fn($a, $b) => $b['count'] <=> $a['count']);

        if (count($chart) > 10) {
            $top = array_slice($chart, 0, 10);
            $other = array_slice($chart, 10);
            $otherCount = array_sum(array_column($other, 'count'));
            if ($otherCount > 0) {
                $top[] = ['code' => 'LAINNYA', 'label' => 'Lainnya', 'count' => $otherCount];
            }
            $chart = $top;
        }

        return $chart;
    }
}
