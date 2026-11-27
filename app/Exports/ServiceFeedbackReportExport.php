<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ServiceFeedbackReportExport implements Export, WithMultipleSheets
{
    public function __construct(
        private readonly Collection $admins,
        private readonly Collection $guests,
    ) {
    }

    public function sheets(): array
    {
        return [
            new ServiceFeedbackAdminExport($this->admins),
            new ServiceFeedbackGuestExport($this->guests),
        ];
    }
}
