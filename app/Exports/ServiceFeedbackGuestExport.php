<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ServiceFeedbackGuestExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Collection $guests)
    {
    }

    public function collection(): Collection
    {
        return $this->guests;
    }

    public function title(): string
    {
        return 'Rating Tamu';
    }

    public function headings(): array
    {
        return ['Waktu Rating', 'Nama Tamu', 'NIP', 'Instansi Tamu', 'Rating', 'Komentar / Saran'];
    }

    public function map($guest): array
    {
        return [
            $guest->feedback_submitted_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            $guest->name,
            $guest->nip ?? '-',
            $guest->agency ?? '-',
            $guest->feedback_rating,
            $guest->feedback_comment ?? '-',
        ];
    }
}
