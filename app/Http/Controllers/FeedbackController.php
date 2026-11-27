<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\ServiceFeedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function create(Guest $guest)
    {
        if ($guest->feedback_rating !== null) {
            return redirect()->route('dashboard')->with('info', 'Feedback sudah pernah diberikan.');
        }

        $staff = $this->serviceStaffForGuest($guest);

        return view('feedbacks.create', compact('guest', 'staff'));
    }

    public function store(Request $request, Guest $guest)
    {
        $staff = $this->serviceStaffForGuest($guest);
        $staffRoles = $this->serviceStaffRoles($guest);
        $validated = $request->validate([
            'feedback_rating'  => 'required|integer|min:1|max:5',
            'feedback_comment' => 'nullable|string|max:500',
            'admin_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('institution_id', $guest->institution_id)
                    ->whereNotNull('nip')
                    ->whereIn('role', $staffRoles),
            ],
        ]);

        $petugas = $staff->firstWhere('id', (int) $validated['admin_user_id']);
        abort_unless($petugas, 422, 'Petugas tidak tersedia untuk instansi tamu ini.');

        DB::transaction(function () use ($guest, $validated, $petugas): void {
            $guest->update([
                'feedback_rating' => $validated['feedback_rating'],
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'feedback_submitted_at' => now(),
            ]);

            ServiceFeedback::create([
                'admin_user_id' => $petugas->id,
                'admin_nip' => $petugas->nip,
                'rating' => $validated['feedback_rating'],
                'comment' => $validated['feedback_comment'] ?? null,
            ]);
        });

        return view('feedbacks.thankyou', compact('guest'));
    }

    private function serviceStaffForGuest(Guest $guest)
    {
        if (!$guest->institution_id) {
            return collect();
        }

        $roles = $this->serviceStaffRoles($guest);

        return User::query()
            ->where('institution_id', $guest->institution_id)
            ->whereNotNull('nip')
            ->whereIn('role', $roles)
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);
    }

    private function serviceStaffRoles(Guest $guest): array
    {
        $departmentRoles = $guest->institution_id
            ? \App\Models\Department::where('institution_id', $guest->institution_id)
                ->whereNotNull('role_key')
                ->pluck('role_key')
                ->all()
            : [];

        return collect(array_keys(User::ACCESS_ROLES))
            ->reject(fn (string $role): bool => $role === 'super_admin')
            ->merge(['admin'])
            ->merge($departmentRoles)
            ->unique()
            ->values()
            ->all();
    }

    public function adminCreate(string $token)
    {
        $admin = $this->findAdminByQrToken($token);
        $feedbackQrUrl = route('feedback.admin.create', $token);

        return view('feedbacks.admin-create', compact('admin', 'token', 'feedbackQrUrl'));
    }

    public function adminStore(Request $request, string $token)
    {
        $validated = $request->validate([
            'feedback_rating' => ['required', 'integer', 'between:1,5'],
            'feedback_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $this->findAdminByQrToken($token);

        ServiceFeedback::create([
            'admin_user_id' => $admin->id,
            'admin_nip' => $admin->nip,
            'rating' => $validated['feedback_rating'],
            'comment' => $validated['feedback_comment'] ?? null,
        ]);

        return view('feedbacks.admin-thankyou');
    }

    private function findAdminByQrToken(string $token): User
    {
        $admin = User::where('feedback_qr_token', $token)->firstOrFail();
        abort_unless($admin->nip && $admin->isAdmin(), 404);

        return $admin;
    }
}
