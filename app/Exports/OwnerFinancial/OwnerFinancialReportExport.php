<?php

namespace App\Exports\OwnerFinancial;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Owner Financial Intelligence → Excel: "Deals" (the table, all pages) + "Summary" (the KPIs). */
class OwnerFinancialReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $rows,
        private array $summary,
        private array $filters,
    ) {
    }

    public function sheets(): array
    {
        return [
            new OwnerFinancialDealsSheet($this->rows),
            new OwnerFinancialSummarySheet($this->summary, $this->filters),
        ];
    }
}
