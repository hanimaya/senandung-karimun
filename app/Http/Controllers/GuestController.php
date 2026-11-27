<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\GuestDocumentHistory;
use App\Models\Employee;
use App\Services\FonnteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $query = Guest::forUser(auth()->user())->latest('entry_time');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('agency', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereNull('exit_time');
            } elseif ($request->status === 'completed') {
                $query->whereNotNull('exit_time');
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        $guests       = $query->paginate(5)->withQueryString();
        $serviceTypes = \App\Models\ServiceType::forUser(auth()->user())->active()->orderBy("sort_order")->pluck("name", "code")->all();
        $destinationFields = \App\Models\Department::forUser(auth()->user())->active()->orderBy('sort_order')->pluck('name')->all();

        return view('guests.index', compact('guests', 'serviceTypes', 'destinationFields'));
    }

    public function create()
    {
        $serviceTypes = \App\Models\ServiceType::forUser(auth()->user())->active()->orderBy("sort_order")->pluck("name", "code")->all();
        $destinationFields = \App\Models\Department::forUser(auth()->user())->active()->orderBy('sort_order')->pluck('name')->all();
        return view('guests.create', compact('serviceTypes', 'destinationFields'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nip'             => 'required_if:employee_status,ASN|string|max:20',
            'name'            => 'required|string|max:150',
            'agency'          => 'nullable|string|max:150',
            'employee_status' => 'required|in:ASN,Non ASN',
            'destination'     => ['required', 'string', Rule::in(\App\Models\Department::forUser(auth()->user())->active()->pluck('name')->all())],
            'documents_submitted' => 'sometimes|boolean',
            'service_type'    => ['required', 'string', 'max:20'],
            'purpose'         => 'nullable|string|max:500',
            'phone'           => 'required|string|max:20',
            'address'         => 'required|string',
            'type'            => 'required|in:Umum,Undangan',
        ]);

        if ($validated['employee_status'] !== 'ASN') {
            $validated['nip'] = null;
        } else {
            $employee = Employee::where('nip', $validated['nip'])->first();
            if ($employee) {
                $validated['name']   = $employee->name;
                $validated['agency'] = $employee->agency;
            }
        }

        $validated['entry_time'] = now();
        $validated['recorded_by'] = auth()->user()->name ?? auth()->user()->email;
        if (!auth()->user()->isSuperAdmin()) {
            $validated['institution_id'] = auth()->user()->institution_id
                ?? \App\Models\Institution::query()->orderBy('id')->value('id');
        }
        $validated['documents_submitted'] = $request->boolean('documents_submitted');

        $guest = DB::transaction(function () use ($validated) {
            $guest = Guest::create($validated);
            if ($guest->documents_submitted) {
                $this->recordDocumentStatus($guest, 'Dikirim');
            }

            return $guest;
        });

        return redirect()->route('guests.index')->with('success', 'Tamu berhasil dicatat!');
    }

    public function show(Guest $guest)
    {
        $serviceTypes = \App\Models\ServiceType::forUser(auth()->user())->active()->orderBy("sort_order")->pluck("name", "code")->all();
        $documentHistoryQuery = $guest->documentHistories()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        $documentHistories = (clone $documentHistoryQuery)
            ->paginate(5, ['*'], 'document_page')
            ->withQueryString();
        $latestDocumentHistory = (clone $documentHistoryQuery)->first();
        $questionnaireResponses = $guest->questionnaireResponses()
            ->with('questionnaire')
            ->latest()
            ->paginate(5, ['*'], 'response_page')
            ->withQueryString();

        return view('guests.show', compact(
            'guest', 'serviceTypes', 'documentHistories', 'latestDocumentHistory', 'questionnaireResponses'
        ));
    }

    public function edit(Guest $guest)
    {
        $serviceTypes = \App\Models\ServiceType::forUser(auth()->user())->active()->orderBy("sort_order")->pluck("name", "code")->all();
        $destinationFields = \App\Models\Department::forUser(auth()->user())->active()->orderBy('sort_order')->pluck('name')->all();
        return view('guests.edit', compact('guest', 'serviceTypes', 'destinationFields'));
    }

    public function update(Request $request, Guest $guest)
    {
        $validated = $request->validate([
            'nip'             => 'required_if:employee_status,ASN|string|max:20',
            'name'            => 'required|string|max:150',
            'agency'          => 'nullable|string|max:150',
            'employee_status' => 'required|in:ASN,Non ASN',
            'destination'     => ['required', 'string', Rule::in(array_unique(array_merge(\App\Models\Department::forUser(auth()->user())->active()->pluck('name')->all(), [$guest->destination])))],
            'documents_submitted' => 'sometimes|boolean',
            'service_type'    => ['required', 'string', 'max:20'],
            'purpose'         => 'nullable|string|max:500',
            'phone'           => 'required|string|max:20',
            'address'         => 'required|string',
            'type'            => 'required|in:Umum,Undangan',
        ]);

        if ($validated['employee_status'] !== 'ASN') {
            $validated['nip'] = null;
        } else {
            $employee = Employee::where('nip', $validated['nip'])->first();
            if ($employee) {
                $validated['name']   = $employee->name;
                $validated['agency'] = $employee->agency;
            }
        }

        DB::transaction(function () use ($request, $guest, $validated) {
            $hasDocumentHistory = $guest->documentHistories()->exists();
            $validated['documents_submitted'] = $request->boolean('documents_submitted') || $hasDocumentHistory;
            $wasSubmitted = $guest->documents_submitted;
            $guest->update($validated);

            if ($guest->documents_submitted && !$wasSubmitted && !$hasDocumentHistory) {
                $this->recordDocumentStatus($guest, 'Dikirim');
            }
        });

        return redirect()->route('guests.index')->with('success', 'Data tamu berhasil diperbarui!');
    }

    public function checkout(Guest $guest, FonnteService $fonnte)
    {
        $guest->update(['exit_time' => now()]);

        // Kirim WhatsApp feedback via Fonnte
        $fonnte->sendFeedbackLink($guest);

        return redirect()->route('guests.index')
            ->with('success', 'Tamu berhasil di-checkout. Pesan WhatsApp kuesioner telah dikirim.');
    }

    public function addDocumentStatus(Request $request, Guest $guest)
    {
        abort_unless($guest->documents_submitted, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Guest::$documentStatuses)],
            'note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($guest, $validated) {
            $guest->documentHistories()->create([
                ...$validated,
                'recorded_by' => auth()->user()->name ?? auth()->user()->email,
            ]);
        });

        return redirect()->route('guests.show', $guest)->with('success', 'Riwayat dokumen berhasil diperbarui.');
    }

    private function recordDocumentStatus(Guest $guest, string $status): GuestDocumentHistory
    {
        return $guest->documentHistories()->create([
            'status' => $status,
            'recorded_by' => auth()->user()->name ?? auth()->user()->email,
        ]);
    }

    public function destroy(Guest $guest)
    {
        $guest->delete();
        return redirect()->route('guests.index')->with('success', 'Data tamu berhasil dihapus!');
    }

    /**
     * AJAX: Cari data pegawai berdasarkan NIP
     */
    public function lookupEmployee(Request $request)
    {
        $nip = $request->input('nip', '');

        if (strlen($nip) < 4) {
            return response()->json(['found' => false]);
        }

        $employee = Employee::where('nip', 'like', "%{$nip}%")->first();

        if ($employee) {
            return response()->json([
                'found'    => true,
                'nip'      => $employee->nip,
                'name'     => $employee->name,
                'agency'   => $employee->agency,
                'position' => $employee->position,
                'rank'     => $employee->rank,
            ]);
        }

        return response()->json(['found' => false]);
    }

    public function servicePortal(Guest $guest)
    {
        $skmUrl = $guest->institution?->skm_url
            ?: config('services.skm.default_url', 'https://skm.go.id/share/instansi/aa50ae94-8722-4b5e-8b0a-a4e3230b7cf7/1');

        $serviceUrl = route('feedback.create', $guest);

        $documentUrl = route('guests.public-document-history', $guest);

        $complaintUrl = route('complaints.create', $guest);
        $complaintHistoryUrl = route('complaints.history', $guest);

        return view('guests.service-portal', compact('guest', 'skmUrl', 'serviceUrl', 'documentUrl', 'complaintUrl', 'complaintHistoryUrl'));
    }

    public function publicDocumentHistory(Guest $guest)
    {
        $documentHistories = $guest->documentHistories()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(5)
            ->withQueryString();

        return view('guests.public-document-history', compact('guest', 'documentHistories'));
    }
}
