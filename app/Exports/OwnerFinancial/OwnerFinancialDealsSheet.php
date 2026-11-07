<?php

namespace App\Exports\OwnerFinancial;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OwnerFinancialDealsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithColumnFormatting, ShouldAutoSize, WithEvents, WithStrictNullComparison
{
    private const MONEY = '#,##0.00';

    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'Deals';
    }

    public function headings(): array
    {
        return [
            'Deal Number', 'Lead Name', 'Property', 'Client', 'Client Phone', 'Agent', 'Branch',
            'Lead Source', 'Deal Type', 'Deal Status', 'Deal Date', 'Date Basis',
            'Deal Amount (AED)', 'Commission Rate %', 'Gross Commission (AED)', 'Agent Commission (AED)',
            'External Commission (AED)', 'Net Company Revenue (AED)', 'Split Source', 'Financial Data', 'Missing Information',
        ];
    }

    public function array(): array
    {
        // Missing values stay empty cells (not 0) so Excel sums don't treat them as real zeros.
        return array_map(fn (array $r) => [
            $r['deal_number'],
            $r['lead_name'] ? $r['lead_name'].($r['lead_archived'] ? ' (archived)' : '') : null,
            $r['property'],
            $r['client']['name'] ?? null,
            $r['client']['phone'] ?? null,
            $r['agent']['name'] ?? null,
            $r['branch'],
            $r['source'],
            $r['deal_type_label'],
            ($r['stage']['name'] ?? '').($r['is_closed'] ? ' (Closed)' : ''),
            $r['deal_date'],
            $r['deal_date_is_won'] ? 'Won date' : 'Created date (won date not recorded)',
            $r['deal_amount'],
            $r['commission_rate'],
            $r['gross_commission'],
            $r['agent_commission'],
            $r['external_commission'],
            $r['net_revenue'],
            $r['commission_source'] === 'breakdown'
                ? 'Deal commission breakdown'
                : 'Agent profile %'.($r['agent_rate'] !== null ? " ({$r['agent_rate']}%)" : ''),
            $r['is_incomplete'] ? 'Incomplete' : 'Complete',
            implode('; ', array_column($r['issues'], 'label')),
        ], $this->rows);
    }

    public function columnFormats(): array
    {
        return [
            'M' => self::MONEY, 'N' => '0.00', 'O' => self::MONEY,
            'P' => self::MONEY, 'Q' => self::MONEY, 'R' => self::MONEY,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '733E87']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('B2');
                $last = count($this->rows) + 1;
                if ($last > 1) {
                    $sheet->setAutoFilter("A1:U{$last}");
                    // Net revenue column: light theme tint.
                    $sheet->getStyle("R2:R{$last}")->getFill()->setFillType('solid')->getStartColor()->setRGB('F7F2F8');
                    $sheet->getStyle("R2:R{$last}")->getFont()->setBold(true);
                }
            },
        ];
    }
}
