<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Daily Leads Report sheet, laid out like the team's Excel:
 *   row 1  title
 *   row 2  "<n> agents" + per-source totals + grand total
 *   row 3  headers (#, Agent Name, sources…, Total) — coloured like the original
 *   row 4+ one row per agent, Total column in yellow
 *
 * Built from the (possibly edited) preview the user sends, not re-queried, so whatever
 * they corrected in the preview is what ends up in the file.
 */
class DailyLeadsReportExport implements FromArray, WithEvents, WithTitle
{
    /** Header colours from the original sheet; every other source header is blue. */
    private const HEADER_COLORS = [
        'bayut' => ['8DC63F', '1E1E1E'],
        'property_finder' => ['E6B8D5', '1E1E1E'],
    ];

    private const BLUE = '4A86E8';
    private const NAVY = '1F3864';
    private const YELLOW = 'FFFF00';

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array{name: string, counts: array<string, int|float|string>}>  $rows
     */
    public function __construct(
        private string $title,
        private array $columns,
        private array $rows,
    ) {}

    public function title(): string
    {
        return 'Daily Leads Report';
    }

    public function array(): array
    {
        $totals = [];
        $body = [];
        foreach ($this->rows as $i => $row) {
            $line = [$i + 1, (string) ($row['name'] ?? '')];
            $sum = 0;
            foreach ($this->columns as $column) {
                $n = (int) ($row['counts'][$column['key']] ?? 0);
                $totals[$column['key']] = ($totals[$column['key']] ?? 0) + $n;
                $sum += $n;
                $line[] = $n ?: null; // blank instead of 0, like the original sheet
            }
            $line[] = $sum;
            $body[] = $line;
        }

        $summary = ['', count($this->rows) . ' agents'];
        foreach ($this->columns as $column) {
            $summary[] = $totals[$column['key']] ?? 0;
        }
        $summary[] = array_sum($totals);

        $header = ['#', 'Agent Name'];
        foreach ($this->columns as $column) {
            $header[] = $column['label'];
        }
        $header[] = 'Total';

        return array_merge([[$this->title], $summary, $header], $body);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = Coordinate::stringFromColumnIndex(count($this->columns) + 3);
                $lastRow = count($this->rows) + 3;
                $totalCol = $lastCol;

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::NAVY);

                // Totals row
                $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Header row
                $sheet->getRowDimension(3)->setRowHeight(32);
                $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BLUE]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle('A3:B3')->getFill()->getStartColor()->setRGB(self::NAVY);
                foreach ($this->columns as $i => $column) {
                    if (! isset(self::HEADER_COLORS[$column['key']])) {
                        continue;
                    }
                    [$bg, $fg] = self::HEADER_COLORS[$column['key']];
                    $cell = Coordinate::stringFromColumnIndex($i + 3) . '3';
                    $sheet->getStyle($cell)->getFill()->getStartColor()->setRGB($bg);
                    $sheet->getStyle($cell)->getFont()->getColor()->setRGB($fg);
                }
                $sheet->getStyle("{$totalCol}3")->getFill()->getStartColor()->setRGB(self::YELLOW);
                $sheet->getStyle("{$totalCol}3")->getFont()->getColor()->setRGB('000000');

                // Body
                if ($lastRow >= 4) {
                    $sheet->getStyle("A4:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle("B4:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("{$totalCol}4:{$totalCol}{$lastRow}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::YELLOW]],
                    ]);
                }

                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(26);
                for ($i = 3; $i <= count($this->columns) + 3; $i++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(13);
                }
                $sheet->freezePane('C4');
            },
        ];
    }
}
