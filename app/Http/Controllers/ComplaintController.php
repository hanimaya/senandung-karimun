<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    public function create(Guest $guest)
    {
        return view('complaints.create', compact('guest'));
    }

    public function store(Request $request, Guest $guest)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'category' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $complaint = Complaint::create([
            'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            ...$validated,
            'guest_id' => $guest->id,
            'institution_id' => $guest->institution_id,
        ]);

        return view('complaints.thankyou', compact('guest', 'complaint'));
    }

    public function index(Request $request)
    {
        $query = Complaint::forUser(auth()->user())
            ->with(['institution', 'guest', 'handler'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $complaints = $query->paginate(10)->withQueryString();
        return view('complaints.index', compact('complaints'));
    }

    public function updateStatus(Request $request, Complaint $complaint)
    {
        $this->ensureCanAccess($complaint);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Complaint::STATUSES))],
            'response' => ['nullable', 'string', 'max:3000'],
        ]);

        $complaint->update([
            ...$validated,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        return back()->with('success', 'Status pengaduan berhasil diperbarui.');
    }

    public function history(Guest $guest)
    {
        $complaints = Complaint::where('guest_id', $guest->id)
            ->with('handler')
            ->latest()
            ->paginate(10);

        return view('complaints.history', compact('guest', 'complaints'));
    }

    private function ensureCanAccess(Complaint $complaint): void
    {
        $user = auth()->user();
        abort_unless(
            $user->isSuperAdmin()
            || ($user->isInstitutionAdmin() && (int) $complaint->institution_id === (int) $user->institution_id),
            403
        );
    }
}
