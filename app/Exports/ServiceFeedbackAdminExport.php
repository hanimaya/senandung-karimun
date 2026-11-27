<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ServiceFeedbackAdminExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Collection $admins)
    {
    }

    public function collection(): Collection
    {
        return $this->admins;
    }

    public function title(): string
    {
        return 'Rating Admin';
    }

    public function headings(): array
    {
        return [
            'NIP Admin',
            'Nama Admin',
            'Jumlah Penilaian',
            'Rata-rata Rating',
            'Rating 1 - Sangat Buruk',
            'Rating 2 - Buruk',
            'Rating 3 - Cukup',
            'Rating 4 - Baik',
            'Rating 5 - Sangat Baik',
        ];
    }

    public function map($admin): array
    {
        return [
            $admin->admin_nip,
            $admin->admin_name,
            (int) $admin->total,
            number_format((float) $admin->average_rating, 2, '.', ''),
            (int) $admin->rating_1,
            (int) $admin->rating_2,
            (int) $admin->rating_3,
            (int) $admin->rating_4,
            (int) $admin->rating_5,
        ];
    }
}
