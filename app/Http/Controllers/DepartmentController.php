<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::forUser(auth()->user())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(5);
        return view('departments.index', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:departments,slug'],
            'role_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:departments,role_key'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Department::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'role_key' => $validated['role_key'] ?: null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'institution_id' => auth()->user()->isSuperAdmin() ? null : auth()->user()->institution_id,
        ]);

        return back()->with('success', 'Bidang/perangkat berhasil ditambahkan.');
    }

    public function update(Request $request, Department $department)
    {
        abort_if(!auth()->user()->isSuperAdmin() && $department->institution_id !== auth()->user()->institution_id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('departments', 'slug')->ignore($department->id)],
            'role_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('departments', 'role_key')->ignore($department->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $department->update([
            ...$validated,
            'role_key' => $validated['role_key'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Bidang/perangkat berhasil diperbarui.');
    }

    public function destroy(Department $department)
    {
        abort_if(!auth()->user()->isSuperAdmin() && $department->institution_id !== auth()->user()->institution_id, 403);

        if (Guest::where('destination', $department->name)->exists()) {
            return back()->with('error', 'Bidang tidak dapat dihapus karena masih digunakan oleh data tamu. Nonaktifkan bidang sebagai gantinya.');
        }

        $department->delete();
        return back()->with('success', 'Bidang/perangkat berhasil dihapus.');
    }
}
