<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceTypeController extends Controller
{
    public function index()
    {
        $serviceTypes = ServiceType::forUser(auth()->user())
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(5);
        return view('service-types.index', compact('serviceTypes'));
    }

    public function store(Request $request)
    {
        $institutionId = auth()->user()->isSuperAdmin()
            ? $request->integer('institution_id')
            : auth()->user()->institution_id;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:250'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'institution_id' => auth()->user()->isSuperAdmin()
                ? ['required', 'integer', 'exists:institutions,id']
                : ['nullable'],
        ]);

        ServiceType::create([
            'institution_id' => $institutionId,
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Jenis pelayanan berhasil ditambahkan.');
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        abort_if(!auth()->user()->isSuperAdmin() && $serviceType->institution_id !== auth()->user()->institution_id, 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('service_types', 'code')->where(fn ($q) => $q->where('institution_id', $serviceType->institution_id))->ignore($serviceType->id)],
            'name' => ['required', 'string', 'max:250'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $serviceType->update([
            ...$validated,
            'code' => strtoupper(trim($validated['code'])),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Jenis pelayanan berhasil diperbarui.');
    }

    public function destroy(ServiceType $serviceType)
    {
        abort_if(!auth()->user()->isSuperAdmin() && $serviceType->institution_id !== auth()->user()->institution_id, 403);
        $serviceType->delete();
        return back()->with('success', 'Jenis pelayanan berhasil dihapus.');
    }
}
