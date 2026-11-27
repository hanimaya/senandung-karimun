<?php

namespace App\Http\Controllers;

use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QuestionnaireController extends Controller
{
    // ─── Super Admin: CRUD ───────────────────────────────────────

    public function index()
    {
        $this->ensureInstitutionContext();
        $questionnaires = Questionnaire::forUser(auth()->user())->withCount('responses')->latest()->paginate(5);
        $activeQuestionnaire = Questionnaire::forUser(auth()->user())->where('is_active', true)->first();
        return view('questionnaires.index', compact('questionnaires', 'activeQuestionnaire'));
    }

    public function create()
    {
        $this->ensureInstitutionContext();
        return view('questionnaires.create');
    }

    public function store(Request $request)
    {
        $this->ensureInstitutionContext();
        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'questions'   => 'required|array|min:1',
            'questions.*.text'    => 'required|string',
            'questions.*.type'    => 'required|in:rating,text,choice',
            'questions.*.options' => 'nullable|array',
        ]);

        $questions = collect($validated['questions'])->map(function ($q, $i) {
            // Normalize options: convert [{text,weight}] format, filter empty
            if (!empty($q['options'])) {
                $q['options'] = collect($q['options'])
                    ->filter(fn($o) => !empty(is_array($o) ? ($o['text'] ?? '') : $o))
                    ->map(fn($o) => is_array($o)
                        ? ['text' => trim($o['text'] ?? ''), 'weight' => (float)($o['weight'] ?? 1)]
                        : ['text' => trim($o), 'weight' => 1]
                    )->values()->toArray();
            }
            return array_merge($q, ['id' => 'q' . ($i + 1)]);
        })->toArray();

        if ($request->boolean('is_active')) {
            $this->questionnaireQueryForCurrentUser()
                ->update(['is_active' => false]);
        }

        Questionnaire::create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'questions'   => $questions,
            'is_active'   => $request->boolean('is_active'),
            'institution_id' => auth()->user()->isSuperAdmin() ? null : auth()->user()->institution_id,
        ]);

        return redirect()->route('questionnaires.index')
            ->with('success', 'Kuesioner berhasil dibuat!');
    }

    public function edit(Questionnaire $questionnaire)
    {
        $this->ensureCanAccessQuestionnaire($questionnaire);
        return view('questionnaires.edit', compact('questionnaire'));
    }

    public function update(Request $request, Questionnaire $questionnaire)
    {
        $this->ensureCanAccessQuestionnaire($questionnaire);
        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'questions'   => 'required|array|min:1',
            'questions.*.text'    => 'required|string',
            'questions.*.type'    => 'required|in:rating,text,choice',
            'questions.*.options' => 'nullable|array',
        ]);

        $questions = collect($validated['questions'])->map(function ($q, $i) {
            // Normalize options
            if (!empty($q['options'])) {
                $q['options'] = collect($q['options'])
                    ->filter(fn($o) => !empty(is_array($o) ? ($o['text'] ?? '') : $o))
                    ->map(fn($o) => is_array($o)
                        ? ['text' => trim($o['text'] ?? ''), 'weight' => (float)($o['weight'] ?? 1)]
                        : ['text' => trim($o), 'weight' => 1]
                    )->values()->toArray();
            }
            return array_merge($q, ['id' => $q['id'] ?? 'q' . ($i + 1)]);
        })->toArray();

        if ($request->boolean('is_active')) {
            Questionnaire::where('institution_id', $questionnaire->institution_id)
                ->where('id', '!=', $questionnaire->id)
                ->update(['is_active' => false]);
        }

        $questionnaire->update([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'questions'   => $questions,
            'is_active'   => $request->boolean('is_active'),
        ]);

        return redirect()->route('questionnaires.index')
            ->with('success', 'Kuesioner berhasil diperbarui!');
    }

    public function destroy(Questionnaire $questionnaire)
    {
        $this->ensureCanAccessQuestionnaire($questionnaire);
        $questionnaire->delete();
        return redirect()->route('questionnaires.index')
            ->with('success', 'Kuesioner berhasil dihapus!');
    }

    /**
     * Hasil kuesioner — per responden + statistik per pertanyaan
     * Rumus skor per responden: total bobot nilai ÷ jumlah soal (rating)
     * Rumus laporan: jumlah nilai responden ÷ total responden
     */
    public function results(Questionnaire $questionnaire, Request $request)
    {
        $this->ensureCanAccessQuestionnaire($questionnaire);
        $period    = $request->input('period', 'all'); // all | weekly | monthly | yearly
        $year      = $request->integer('year',  now()->year);
        $month     = $request->integer('month', now()->month);
        $week      = $request->integer('week',  now()->weekOfYear);

        $query = $questionnaire->responses()->with('guest')->latest();

        // Filter periode
        if ($period === 'weekly') {
            $start = Carbon::now()->setISODate($year, $week)->startOfWeek();
            $end   = $start->copy()->endOfWeek();
            $query->whereBetween('created_at', [$start, $end]);
            $periodLabel = "Minggu ke-{$week}, {$year}";
        } elseif ($period === 'monthly') {
            $start = Carbon::create($year, $month)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
            $periodLabel = Carbon::create($year, $month)->translatedFormat('F Y');
        } elseif ($period === 'yearly') {
            $start = Carbon::create($year)->startOfYear();
            $end   = $start->copy()->endOfYear();
            $query->whereBetween('created_at', [$start, $end]);
            $periodLabel = "Tahun {$year}";
        } else {
            $start = null;
            $end   = null;
            $periodLabel = 'Semua Waktu';
        }

        $responses = $query->paginate(5)->withQueryString();
        $allResponses = $questionnaire->responses()
            ->when($start, fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->get();

        $total = $allResponses->count();

        // Rata-rata laporan: Σ skor responden / total responden
        $avgScore = $total > 0
            ? round($allResponses->whereNotNull('score')->avg('score') ?? 0, 2)
            : 0;

        // Rekap per pertanyaan (rating + choice berbobot, teks bebas tidak dihitung)
        $questionStats = [];
        foreach ($questionnaire->questions as $q) {
            if ($q['type'] === 'rating') {
                $vals = $allResponses->pluck("answers.{$q['id']}")->filter()->map(fn($v) => (float) $v);
                $questionStats[$q['id']] = [
                    'text'  => $q['text'],
                    'type'  => 'rating',
                    'avg'   => $vals->count() ? round($vals->avg(), 2) : null,
                    'count' => $vals->count(),
                ];
            } elseif ($q['type'] === 'choice' && !empty($q['options'])) {
                // Hitung distribusi dan rata-rata bobot pilihan ganda
                $optionMap = collect($q['options'])->keyBy(fn($o) => is_array($o) ? ($o['text'] ?? '') : $o);
                $vals = $allResponses->pluck("answers.{$q['id']}")->filter();
                $weightedTotal = 0;
                $counted = 0;
                $distribution = [];
                foreach ($vals as $chosen) {
                    $opt = $optionMap->get($chosen);
                    $w = $opt ? (float)(is_array($opt) ? ($opt['weight'] ?? 1) : 1) : 0;
                    $weightedTotal += $w;
                    $counted++;
                    $distribution[$chosen] = ($distribution[$chosen] ?? 0) + 1;
                }
                $questionStats[$q['id']] = [
                    'text'         => $q['text'],
                    'type'         => 'choice',
                    'options'      => $q['options'],
                    'avg'          => $counted ? round($weightedTotal / $counted, 2) : null,
                    'count'        => $counted,
                    'distribution' => $distribution,
                ];
            }
        }

        // ── Hitung maxScore: bobot tertinggi dari seluruh pertanyaan berbobot ──
        $maxScore = 4; // default untuk rating (skala 1-4)
        foreach ($questionnaire->questions as $q) {
            if ($q['type'] === 'rating') {
                // rating skala 1-4, max = 4
            } elseif ($q['type'] === 'choice' && !empty($q['options'])) {
                foreach ($q['options'] as $opt) {
                    $w = is_array($opt) ? (float)($opt['weight'] ?? 1) : 1;
                    if ($w > $maxScore) $maxScore = $w;
                }
            }
        }
        // Jika hanya ada choice (tidak ada rating), ambil max dari choice options saja
        $hasRating = collect($questionnaire->questions)->where('type', 'rating')->isNotEmpty();
        if (!$hasRating) {
            $maxScore = 1;
            foreach ($questionnaire->questions as $q) {
                if ($q['type'] === 'choice' && !empty($q['options'])) {
                    foreach ($q['options'] as $opt) {
                        $w = is_array($opt) ? (float)($opt['weight'] ?? 1) : 1;
                        if ($w > $maxScore) $maxScore = $w;
                    }
                }
            }
        }

        // ── Hitung avgScore langsung dari questionStats (lebih akurat, tidak bergantung kolom score) ──
        $avgScore = 0;
        if (count($questionStats) > 0) {
            $sumWeighted = 0;
            $sumCount    = 0;
            foreach ($questionStats as $stat) {
                if ($stat['avg'] !== null && $stat['count'] > 0) {
                    $sumWeighted += $stat['avg'] * $stat['count'];
                    $sumCount    += $stat['count'];
                }
            }
            $avgScore = $sumCount > 0 ? round($sumWeighted / $sumCount, 2) : 0;
        }

        // Navigasi periode
        $prevWeek  = $week > 1  ? $week - 1  : 52;
        $prevWYear = $week > 1  ? $year       : $year - 1;
        $nextWeek  = $week < 52 ? $week + 1  : 1;
        $nextWYear = $week < 52 ? $year       : $year + 1;
        $prevMonth = $month > 1  ? $month - 1 : 12;
        $prevMYear = $month > 1  ? $year       : $year - 1;
        $nextMonth = $month < 12 ? $month + 1 : 1;
        $nextMYear = $month < 12 ? $year       : $year + 1;

        return view('questionnaires.results', compact(
            'questionnaire', 'responses', 'avgScore', 'maxScore', 'total', 'questionStats',
            'period', 'periodLabel', 'year', 'month', 'week',
            'prevWeek', 'prevWYear', 'nextWeek', 'nextWYear',
            'prevMonth', 'prevMYear', 'nextMonth', 'nextMYear'
        ));
    }

    // ─── Public: Tamu isi kuesioner ───────────────────────────────

    public function respond(Questionnaire $questionnaire, Request $request)
    {
        if (!$questionnaire->is_active) {
            abort(404, 'Kuesioner tidak tersedia.');
        }

        $guest = $request->filled('guest')
            ? Guest::find($request->guest)
            : null;
        $this->ensureGuestMatchesQuestionnaire($questionnaire, $guest);

        // ── Cek apakah sudah pernah mengisi ──
        $alreadyResponded = false;

        if ($guest) {
            // Tamu teridentifikasi: cek berdasarkan guest_id
            $alreadyResponded = QuestionnaireResponse::where('questionnaire_id', $questionnaire->id)
                ->where('guest_id', $guest->id)
                ->exists();
        } else {
            // Anonim: cek berdasarkan cookie token
            $token = $request->cookie('qr_token_' . $questionnaire->id);
            if ($token) {
                $alreadyResponded = QuestionnaireResponse::where('questionnaire_id', $questionnaire->id)
                    ->where('respondent_token', $token)
                    ->exists();
            }
        }

        if ($alreadyResponded) {
            return view('questionnaires.already_responded', compact('questionnaire', 'guest'));
        }

        return view('questionnaires.respond', compact('questionnaire', 'guest'));
    }

    public function submitResponse(Questionnaire $questionnaire, Request $request)
    {
        if (!$questionnaire->is_active) {
            abort(404, 'Kuesioner tidak tersedia.');
        }

        $request->validate([
            'respondent_name' => 'nullable|string|max:150',
            'answers'         => 'required|array',
        ]);

        $answers = $request->input('answers', []);

        $guest = $request->filled('guest_id')
            ? Guest::find($request->guest_id)
            : null;
        $this->ensureGuestMatchesQuestionnaire($questionnaire, $guest);

        // ── Cek duplikasi sebelum simpan ──
        if ($guest) {
            $exists = QuestionnaireResponse::where('questionnaire_id', $questionnaire->id)
                ->where('guest_id', $guest->id)
                ->exists();
            if ($exists) {
                return redirect()->route('questionnaire.respond', ['questionnaire' => $questionnaire->id, 'guest' => $guest->id])
                    ->with('already_responded', true);
            }
        } else {
            $token = $request->cookie('qr_token_' . $questionnaire->id);
            if ($token) {
                $exists = QuestionnaireResponse::where('questionnaire_id', $questionnaire->id)
                    ->where('respondent_token', $token)
                    ->exists();
                if ($exists) {
                    return redirect()->route('questionnaire.respond', $questionnaire)
                        ->with('already_responded', true);
                }
            }
        }

        // Buat token anonim baru jika belum ada
        $respondentToken = null;
        if (!$guest) {
            $respondentToken = $request->cookie('qr_token_' . $questionnaire->id) ?? \Illuminate\Support\Str::uuid()->toString();
        }

        // Hitung skor: rating + choice (pilihan ganda berbobot), teks bebas tidak dihitung
        $scorableQs = collect($questionnaire->questions)->whereIn('type', ['rating', 'choice']);
        $score = null;
        if ($scorableQs->count() > 0) {
            $totalBobot = 0;
            $n = 0;
            foreach ($scorableQs as $q) {
                $val = $answers[$q['id']] ?? null;
                if ($val === null) continue;

                if ($q['type'] === 'rating') {
                    $totalBobot += (float) $val;
                    $n++;
                } elseif ($q['type'] === 'choice' && !empty($q['options'])) {
                    // Cari bobot dari pilihan yang dipilih
                    foreach ($q['options'] as $opt) {
                        $optText = is_array($opt) ? ($opt['text'] ?? '') : $opt;
                        if ($optText === $val) {
                            $w = is_array($opt) ? (float)($opt['weight'] ?? 1) : 1;
                            $totalBobot += $w;
                            $n++;
                            break;
                        }
                    }
                }
            }
            if ($n > 0) {
                $score = round($totalBobot / $n, 2);
            }
        }

        $response = QuestionnaireResponse::create([
            'questionnaire_id' => $questionnaire->id,
            'guest_id'         => $guest?->id,
            'respondent_name'  => $request->respondent_name ?? $guest?->name,
            'respondent_token' => $respondentToken,
            'answers'          => $answers,
            'score'            => $score,
        ]);

        // Simpan di session biasa (bukan flash) agar data tersedia melewati halaman rating
        session()->put('pending_response_id',    $response->id);
        session()->put('pending_respondent_name', $response->respondent_name ?? ($guest?->name ?? 'Anonim'));
        session()->put('pending_answers',         $answers);
        session()->put('pending_score',           $score);
        session()->put('pending_guest_id',        $guest?->id);

        $redirect = redirect()->route('questionnaire.rate', $questionnaire);

        // Set cookie token untuk anonim (365 hari)
        if ($respondentToken && !$guest) {
            $redirect = $redirect->cookie(
                'qr_token_' . $questionnaire->id,
                $respondentToken,
                60 * 24 * 365
            );
        }

        return $redirect;
    }

    /**
     * Halaman pemberian bintang / star rating kepuasan tamu
     */
    public function showRating(Questionnaire $questionnaire)
    {
        $respondentName = session('pending_respondent_name', 'Tamu');
        $guestId        = session('pending_guest_id');
        $guest          = $guestId ? Guest::find($guestId) : null;

        // Jika tidak ada data pending (akses langsung tanpa mengisi kuesioner)
        if (!session()->has('pending_response_id')) {
            return redirect()->route('questionnaire.respond', $questionnaire);
        }

        return view('questionnaires.rate', compact('questionnaire', 'respondentName', 'guest'));
    }

    /**
     * Simpan bintang rating dan lanjut ke halaman terima kasih
     */
    public function submitRating(Questionnaire $questionnaire, Request $request)
    {
        $request->validate(['rating' => 'nullable|integer|min:1|max:4']);

        $guestId = session()->pull('pending_guest_id');

        // Simpan feedback_rating ke tamu jika ada
        if ($guestId && $request->filled('rating')) {
            Guest::where('id', $guestId)->update([
                'feedback_rating' => $request->rating,
                'feedback_submitted_at' => now(),
            ]);
        }

        // Pindahkan dari session biasa ke flash untuk halaman terima kasih
        session()->flash('response_id',    session()->pull('pending_response_id'));
        session()->flash('respondent_name', session()->pull('pending_respondent_name', 'Tamu'));
        session()->flash('answers',         session()->pull('pending_answers', []));
        session()->flash('score',           session()->pull('pending_score'));

        return redirect()->route('questionnaire.thankyou', $questionnaire);
    }

    /**
     * Halaman terima kasih + ringkasan jawaban (untuk di-print)
     */
    public function thankyou(Questionnaire $questionnaire)
    {
        $respondentName = session('respondent_name', 'Tamu');
        $answers        = session('answers', []);
        $score          = session('score');
        $responseId     = session('response_id');

        return view('questionnaires.thankyou', compact(
            'questionnaire', 'respondentName', 'answers', 'score', 'responseId'
        ));
    }

    /**
     * Export seluruh responden kuesioner ke CSV (Excel-compatible)
     */
    public function exportResponsesExcel(Questionnaire $questionnaire, Request $request)
    {
        $this->ensureCanAccessQuestionnaire($questionnaire);
        $period = $request->input('period', 'all');
        $year   = $request->integer('year',  now()->year);
        $month  = $request->integer('month', now()->month);
        $week   = $request->integer('week',  now()->weekOfYear);

        $query = $questionnaire->responses()->with('guest')->latest();

        if ($period === 'weekly') {
            $start = Carbon::now()->setISODate($year, $week)->startOfWeek();
            $end   = $start->copy()->endOfWeek();
            $query->whereBetween('created_at', [$start, $end]);
            $label = "Minggu ke-{$week}, {$year}";
        } elseif ($period === 'monthly') {
            $start = Carbon::create($year, $month)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
            $label = Carbon::create($year, $month)->translatedFormat('F Y');
        } elseif ($period === 'yearly') {
            $start = Carbon::create($year)->startOfYear();
            $end   = $start->copy()->endOfYear();
            $query->whereBetween('created_at', [$start, $end]);
            $label = "Tahun {$year}";
        } else {
            $label = 'Semua Waktu';
        }

        $responses = $query->get();
        $questions = $questionnaire->questions;

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $questionnaire->title);
        $filename = "Responden_{$safeName}_" . str_replace([' ', ','], '_', $label) . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        ];

        $callback = function () use ($responses, $questions, $questionnaire, $label) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM agar Excel membaca karakter Indonesia dengan benar
            fputs($handle, "\xEF\xBB\xBF");

            // Baris judul laporan
            fputcsv($handle, ['BKPSDM KABUPATEN KARIMUN — LAPORAN RESPONDEN KUESIONER'], ';');
            fputcsv($handle, [$questionnaire->title], ';');
            fputcsv($handle, ["Periode: {$label}"], ';');
            fputcsv($handle, ["Diekspor: " . now()->timezone('Asia/Jakarta')->format('d F Y H:i') . ' WIB'], ';');
            fputcsv($handle, [], ';');

            // Header kolom
            $headers = ['No', 'Nama Responden', 'Instansi', 'Skor IKM'];
            foreach ($questions as $q) {
                $headers[] = $q['text'];
            }
            $headers[] = 'Waktu Pengisian';
            fputcsv($handle, $headers, ';');

            foreach ($responses as $i => $resp) {
                $row = [
                    $i + 1,
                    $resp->respondent_name ?: ($resp->guest?->name ?: 'Anonim'),
                    $resp->guest?->agency ?: '-',
                    $resp->score !== null ? number_format($resp->score, 2) : '-',
                ];
                foreach ($questions as $q) {
                    $ans = $resp->answers[$q['id']] ?? '-';
                    $row[] = $ans;
                }
                $row[] = $resp->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i');
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function ensureInstitutionContext(): void
    {
        $user = auth()->user();
        abort_unless($user->isSuperAdmin() || ($user->isInstitutionAdmin() && $user->institution_id), 403);
    }

    private function ensureCanAccessQuestionnaire(Questionnaire $questionnaire): void
    {
        $user = auth()->user();
        $this->ensureInstitutionContext();

        abort_unless(
            $user->isSuperAdmin()
                || (int) $questionnaire->institution_id === (int) $user->institution_id,
            403
        );
    }

    private function ensureGuestMatchesQuestionnaire(Questionnaire $questionnaire, ?Guest $guest): void
    {
        abort_unless(
            !$questionnaire->institution_id
                || !$guest
                || (int) $questionnaire->institution_id === (int) $guest->institution_id,
            404
        );
    }

    private function questionnaireQueryForCurrentUser()
    {
        $user = auth()->user();
        return $user->isSuperAdmin()
            ? Questionnaire::whereNull('institution_id')
            : Questionnaire::forUser($user);
    }
}
