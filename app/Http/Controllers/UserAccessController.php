<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Institution;
use App\Models\User;
use App\Services\FonnteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UserAccessController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureInstitutionContext();
        $currentUser = auth()->user();
        $search = trim($request->input('search', ''));
        $usersQuery = User::query();

        if ($currentUser->isInstitutionAdmin()) {
            $usersQuery->where('institution_id', $currentUser->institution_id);
        }

        if ($search !== '') {
            $matchingRoles = collect(User::availableAccessRoles())
                ->filter(fn (string $label) => str_contains(mb_strtolower($label), mb_strtolower($search)))
                ->keys();

            $usersQuery->where(function ($query) use ($search, $matchingRoles) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");

                if ($matchingRoles->isNotEmpty()) {
                    $query->orWhereIn('role', $matchingRoles);
                }
            });
        }

        $users = $usersQuery->orderBy('name')->paginate(5)->withQueryString();
        $accessRoles = $this->accessRoleOptions();

        return view('user-access.index', compact('users', 'search', 'accessRoles'));
    }

    public function edit(User $user)
    {
        $this->ensureCanManageUser($user);
        $accessRoles = $this->accessRoleOptions();

        return view('user-access.edit', compact('user', 'accessRoles'));
    }

    public function lookupEmployee(Request $request)
    {
        $this->ensureInstitutionContext();
        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:20'],
        ]);

        $employee = Employee::where('nip', $validated['nip'])->first();

        if (!$employee) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'name' => $employee->name,
            'assigned' => User::where('nip', $employee->nip)->exists(),
        ]);
    }

    public function store(Request $request, FonnteService $fonnte)
    {
        $this->ensureInstitutionContext();
        $request->merge([
            'whatsapp' => $fonnte->normalizePhone($request->input('whatsapp', '')),
        ]);

        $validated = $request->validate([
            'nip' => ['required', 'string', 'max:20', 'exists:employees,nip', 'unique:users,nip'],
            'whatsapp' => ['required', 'regex:/^62[0-9]{8,13}$/', 'unique:users,whatsapp'],
            'role' => ['required', Rule::in(array_keys($this->accessRoleOptions()))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'institution_id' => auth()->user()->isSuperAdmin()
                ? ['nullable', 'integer', 'exists:institutions,id']
                : ['prohibited'],
        ], [
            'nip.exists' => 'NIP tidak ditemukan pada data pegawai.',
            'nip.unique' => 'Pegawai dengan NIP tersebut sudah memiliki akses.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid. Gunakan nomor Indonesia yang aktif.',
            'whatsapp.unique' => 'Nomor WhatsApp tersebut sudah digunakan akun lain.',
            'role.in' => 'Hak akses yang dipilih tidak valid.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'institution_id.exists' => 'Institusi yang dipilih tidak valid.',
        ]);

        $employee = Employee::where('nip', $validated['nip'])->firstOrFail();

        $user = User::create([
            'nip' => $employee->nip,
            'name' => $employee->name,
            'whatsapp' => $validated['whatsapp'],
            'feedback_qr_token' => Str::random(48),
            // Email tetap disimpan untuk kompatibilitas akun lama; pengguna masuk menggunakan NIP.
            'email' => $employee->nip.'@login.senandung.local',
            'password' => $validated['password'],
            'role' => $validated['role'],
            'institution_id' => auth()->user()->isSuperAdmin()
                ? ($validated['institution_id'] ?? Institution::query()->orderBy('id')->value('id'))
                : auth()->user()->institution_id,
        ]);

        if (!$fonnte->sendAccessCredentials($user, $validated['password'])) {
            return redirect()->route('user-access.index')
                ->with('error', 'Akun berhasil dibuat, tetapi pesan WhatsApp gagal dikirim. Periksa konfigurasi Fonnte dan nomor pengguna.');
        }

        return redirect()->route('user-access.index')
            ->with('success', 'Akses pengguna berhasil ditambahkan dan kredensial dikirim melalui WhatsApp.');
    }

    public function update(Request $request, FonnteService $fonnte, User $user)
    {
        $this->ensureCanManageUser($user);

        $whatsapp = trim((string) $request->input('whatsapp', ''));
        $normalizedWhatsapp = $whatsapp === '' ? null : $fonnte->normalizePhone($whatsapp);
        $request->merge([
            'whatsapp' => $whatsapp === '' ? null : ($normalizedWhatsapp ?? $whatsapp),
        ]);

        $validated = $request->validate([
            'role' => ['required', Rule::in(array_keys($this->accessRoleOptions()))],
            'whatsapp' => ['nullable', 'regex:/^62[0-9]{8,13}$/', Rule::unique('users', 'whatsapp')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'institution_id' => auth()->user()->isSuperAdmin()
                ? ['nullable', 'integer', 'exists:institutions,id']
                : ['prohibited'],
        ], [
            'role.in' => 'Hak akses yang dipilih tidak valid.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'institution_id.exists' => 'Institusi yang dipilih tidak valid.',
        ]);

        if (
            $user->role === 'super_admin'
            && $validated['role'] !== 'super_admin'
            && User::where('role', 'super_admin')->count() <= 1
        ) {
            return back()->withErrors([
                'role' => 'Hak akses Super Admin tidak dapat dihapus karena akun ini adalah Super Admin terakhir.',
            ])->withInput();
        }

        $user->role = $validated['role'];
        $user->whatsapp = $validated['whatsapp'] ?? null;
        $user->institution_id = auth()->user()->isSuperAdmin()
            ? ($validated['institution_id'] ?? Institution::query()->orderBy('id')->value('id'))
            : auth()->user()->institution_id;

        $plainPassword = null;
        if (!empty($validated['password'])) {
            $plainPassword = $validated['password'];
            $user->password = $plainPassword;
        }
        $user->save();

        if ($plainPassword !== null) {
            if (!$fonnte->sendAccessCredentials($user, $plainPassword, 'diperbarui')) {
                return redirect()->route('user-access.index')
                    ->with('error', 'Akses pengguna berhasil diperbarui, tetapi kata sandi baru gagal dikirim melalui WhatsApp.');
            }

            return redirect()->route('user-access.index')
                ->with('success', 'Akses pengguna berhasil diperbarui dan kata sandi baru dikirim melalui WhatsApp.');
        }

        return redirect()->route('user-access.index')
            ->with('success', 'Akses pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->ensureCanManageUser($user);

        if (
            $user->role === 'super_admin'
            && User::where('role', 'super_admin')->count() <= 1
        ) {
            return redirect()->route('user-access.index')
                ->with('error', 'Akun Super Admin terakhir tidak dapat dihapus.');
        }

        $user->delete();

        return redirect()->route('user-access.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }

    private function ensureInstitutionContext(): void
    {
        $user = auth()->user();
        abort_unless($user->isSuperAdmin() || ($user->isInstitutionAdmin() && $user->institution_id), 403);
    }

    private function ensureCanManageUser(User $target): void
    {
        $currentUser = auth()->user();
        $this->ensureInstitutionContext();

        abort_if($target->is($currentUser), 403, 'Akun yang sedang digunakan tidak dapat dikelola dari menu ini.');

        if ($currentUser->isInstitutionAdmin()) {
            abort_unless((int) $target->institution_id === (int) $currentUser->institution_id, 403);
            abort_if(in_array($target->role, ['super_admin', 'admin', 'admin_instansi'], true), 403);
        }
    }

    private function accessRoleOptions(): array
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return User::availableAccessRoles();
        }

        return \App\Models\Department::forUser($user)
            ->active()
            ->whereNotNull('role_key')
            ->orderBy('sort_order')
            ->pluck('name', 'role_key')
            ->map(fn (string $name): string => 'Admin ' . $name)
            ->all();
    }
}
