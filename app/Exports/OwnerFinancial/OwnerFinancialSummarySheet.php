<?php

namespace App\Exports\OwnerFinancial;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OwnerFinancialSummarySheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize, WithStrictNullComparison
{
    public function __construct(private array $summary, private array $filters)
    {
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $s = $this->summary;
        $basis = $s['incomplete_deals']
            ? "From {$s['complete_deals']} of {$s['deals']} deals with complete financial data"
            : "From all {$s['deals']} deals";

        $rows = [
            ['Owner Financial Intelligence'],
            ['Exported', now('Asia/Dubai')->format('Y-m-d H:i').' (UAE)'],
            [''],
            ['KPI', 'Value', 'Note'],
            ['Total Net Company Revenue (AED)', $s['net_revenue'], $basis],
            ['Total Gross Commission (AED)', $s['gross_commission'], "{$s['gross_deals']} of {$s['deals']} deals have amount and commission rate"],
            ['Total Agent Commission (AED)', $s['agent_commission'], $basis],
            ['Total External Commission (AED)', $s['external_commission'], $basis],
            ['Total Closed Deals', $s['closed_deals'], 'Deals in a "Deal Won" stage'],
            ['Missing Financial Data (deals)', $s['incomplete_deals'], 'Not included in agent / external / net totals'],
            ['Deals in report', $s['deals'], ''],
            [''],
            ['Filters'],
        ];

        foreach ($this->filters as $label => $value) {
            $rows[] = [$label, $value];
        }
        if (! $this->filters) {
            $rows[] = ['None', ''];
        }

        $rows[] = [''];
        $rows[] = ['Collected amount, outstanding amount and expenses are not included: the CRM has no payment ledger.'];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('B5:B8')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A5:B5')->getFont()->setBold(true);
        $sheet->getStyle('A5:C5')->getFill()->setFillType('solid')->getStartColor()->setRGB('F7F2F8');

        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '733E87']]],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '733E87']],
            ],
            13 => ['font' => ['bold' => true, 'color' => ['rgb' => '733E87']]],
        ];
    }
}
