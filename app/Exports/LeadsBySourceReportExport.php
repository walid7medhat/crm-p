<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeadsBySourceReportExport implements FromArray, WithHeadings
{
    protected array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->report as $stage) {
            foreach ($stage['sources'] as $source) {
                $rows[] = [
                    $stage['stage_name'],
                    $source['source'],
                    $source['count'],
                ];
            }

            $rows[] = [
                $stage['stage_name'] . ' - Total',
                '',
                $stage['total'],
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Stage',
            'Source',
            'Count',
        ];
    }
}
