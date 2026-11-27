<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InstitutionController extends Controller
{
    public function index()
    {
        $institutions = Institution::orderBy('name')->paginate(5)->withQueryString();
        return view('institutions.index', compact('institutions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:institutions,code'],
            'skm_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Institution::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?: Str::slug($validated['name']),
            'skm_url' => $validated['skm_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Instansi/OPD berhasil ditambahkan.');
    }

    public function update(Request $request, Institution $institution)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('institutions', 'code')->ignore($institution->id)],
            'skm_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $institution->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Instansi/OPD berhasil diperbarui.');
    }

    public function profile()
    {
        $institution = auth()->user()->institution;
        abort_unless($institution, 403, 'Akun belum dikaitkan dengan instansi.');

        return view('institutions.profile', compact('institution'));
    }

    public function updateProfile(Request $request)
    {
        $institution = auth()->user()->institution;
        abort_unless($institution, 403, 'Akun belum dikaitkan dengan instansi.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('institutions', 'code')->ignore($institution->id)],
            'skm_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $institution->update($validated);

        return back()->with('success', 'Profil instansi berhasil diperbarui.');
    }
}
