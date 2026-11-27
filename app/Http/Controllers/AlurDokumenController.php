<?php

namespace App\Http\Controllers;

use App\Models\Guest;

class AlurDokumenController extends Controller
{
    public function pengadaan()
    {
        return $this->documentFlow('Bidang Pengadaan, Pemberhentian, Informasi dan Fasilitasi ASN', 'alur-dokumen.pengadaan');
    }

    public function kinerja()
    {
        return $this->documentFlow('Bidang Penilaian Kinerja Aparatur dan Penghargaan', 'alur-dokumen.kinerja');
    }

    public function kompetensi()
    {
        return $this->documentFlow('Bidang Pengembangan Aparatur', 'alur-dokumen.kompetensi');
    }

    public function sekretariat()
    {
        return $this->documentFlow('Sekretariat BKPSDM', 'alur-dokumen.sekretariat');
    }

    public function show(\App\Models\Department $department)
    {
        abort_unless($department->is_active, 404);

        $user = auth()->user();
        $sameInstitution = $department->institution_id === $user->institution_id;
        $canAccessByRole = $user->role === 'admin'
            || ($sameInstitution && $department->role_key === $user->role);
        $canAccessAsInstitutionAdmin = $user->isInstitutionAdmin() && $sameInstitution;

        if (!$user->isSuperAdmin() && !$canAccessByRole && !$canAccessAsInstitutionAdmin) {
            abort(403);
        }

        return $this->documentFlow($department->name, 'alur-dokumen.dynamic');
    }

    private function documentFlow(string $destination, string $view)
    {
        $guests = Guest::forUser(auth()->user())
            ->where('documents_submitted', true)
            ->where('destination', $destination)
            ->with('documentHistories')
            ->latest('entry_time')
            ->paginate(5)->withQueryString();

        return view($view, compact('guests'));
    }
}

