<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Accountant commission sheet — rows from App\Services\Reports\DealCommissionSheet.
 * Derived money columns are live Excel formulas, so when the accountant fills the blank
 * columns (paid amounts, TL / senior share…) the payable and company-share totals update.
 */
class DealCommissionSheetExport extends DefaultValueBinder implements FromArray, WithEvents, WithTitle, WithStrictNullComparison, WithCustomValueBinder
{
    /** Kept as text: "0547572734" must not become 547572734, codes/unit numbers stay as typed. */
    private const TEXT_KEYS = ['emp_code', 'buyer_phone', 'unit_no', 'rooms'];

    private const MONEY = '#,##0.00';
    private const HEADER_FILL = 'BDE3F2';
    private const TOTAL_FILL = 'EAF5FB';

    /** [heading, width] — order = column order A, B, C… */
    private const COLUMNS = [
        'date' => ['Date', 13],
        'deal_type' => ['Deal type', 11],
        'team_leader' => ['Team Leader', 20],
        'emp_code' => ['Emp Code', 12],
        'agent' => ['Agent Name', 22],
        'seller' => ['Seller Name', 30],
        'buyer' => ['Buyer Name', 30],
        'buyer_phone' => ['Buyer Mobile', 16],
        'buyer_email' => ['Buyer Email ID', 28],
        'unit_type' => ['Unit Type', 13],
        'unit_no' => ['Unit No.', 16],
        'rooms' => ['No. of Rooms', 10],
        'project' => ['Project', 24],
        'source' => ['Lead Source', 16],
        'amount' => ['Unit Price', 16],
        'rate' => ['Commission', 11],
        'commission' => ['Commission amount', 16],
        'referral' => ['Referral', 14],
        'after_referral' => ['Commission After Referral', 17],
        'received' => ['AMT Received after Dis', 17],
        'broker_share' => ['Broker Share', 15],
        'broker_pct' => ['%age', 9],
        'bs_paid' => ['BS Paid', 13],
        'bs_payable' => ['BS Payable', 13],
        'listing_coordinator' => ['Listing Coordinator', 18],
        'tl_share' => ['Team Leader Share', 15],
        'tl_paid' => ['TL Paid', 13],
        'tl_payable' => ['TL Payable', 13],
        'senior_share' => ['Senior Share', 13],
        'ss_paid' => ['SS Paid', 13],
        'ss_payable' => ['SS Payable', 13],
        'company_share' => ['Company Share', 16],
        'notes' => ['Notes', 24],
        'listing_agent' => ['Listing Agent', 20],
        'spa' => ['SPA', 7],
    ];

    /** Columns summed in the totals row. */
    private const TOTAL_KEYS = [
        'amount', 'commission', 'referral', 'after_referral', 'received', 'broker_share',
        'bs_paid', 'bs_payable', 'tl_share', 'tl_paid', 'tl_payable',
        'senior_share', 'ss_paid', 'ss_payable', 'company_share',
    ];

    private const MONEY_KEYS = [
        'amount', 'commission', 'referral', 'after_referral', 'received', 'broker_share',
        'bs_paid', 'bs_payable', 'tl_share', 'tl_paid', 'tl_payable',
        'senior_share', 'ss_paid', 'ss_payable', 'company_share',
    ];

    /** @var array<string, string> key => column letter */
    private array $col = [];

    public function __construct(private array $rows)
    {
        $i = 1;
        foreach (array_keys(self::COLUMNS) as $key) {
            $this->col[$key] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i++);
        }
    }

    public function title(): string
    {
        return 'Deals Commission';
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getRow() > 1 && $value !== null && $value !== ''
            && in_array($cell->getColumn(), array_map(fn ($k) => $this->col[$k], self::TEXT_KEYS), true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function array(): array
    {
        $out = [array_map(fn ($c) => $c[0], self::COLUMNS)];

        foreach (array_values($this->rows) as $i => $r) {
            $n = $i + 2; // sheet row (1 = header)
            $c = fn (string $key) => $this->col[$key].$n;

            $out[] = [
                $r['date'] ? ExcelDate::PHPToExcel($r['date']) : null,
                $r['deal_type'],
                $r['team_leader'],
                $r['emp_code'],
                $r['agent'],
                $r['seller'],
                $r['buyer'],
                $r['buyer_phone'],
                $r['buyer_email'],
                $r['unit_type'],
                $r['unit_no'],
                $r['rooms'],
                $r['project'],
                $r['source'],
                $r['amount'],
                $r['rate'],
                // Commission amount = Unit Price × Commission %
                "=IF(OR({$c('amount')}=\"\",{$c('rate')}=\"\"),\"\",ROUND({$c('amount')}*{$c('rate')},2))",
                $r['referral'],
                "=IF({$c('commission')}=\"\",\"\",{$c('commission')}-N({$c('referral')}))",
                // Received after discount — defaults to the after-referral amount; overwrite if different.
                "={$c('after_referral')}",
                $r['broker_share'],
                "=IF(OR(N({$c('received')})=0,{$c('broker_share')}=\"\"),\"\",{$c('broker_share')}/{$c('received')})",
                null, // BS Paid
                null, // BS Payable
                null, // Listing Coordinator
                null, // Team Leader Share
                null, // TL Paid
                null, // TL Payable
                null, // Senior Share
                null, // SS Paid
                null, // SS Payable
                // Company Share = received − broker − team leader − senior
                "=IF({$c('received')}=\"\",\"\",N({$c('received')})-N({$c('broker_share')})-N({$c('tl_share')})-N({$c('senior_share')}))",
                null, // Notes
                $r['listing_agent'],
                $r['spa'] ? '✓' : '',
            ];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $this->col['spa'];
                $lastRow = count($this->rows) + 1;
                $totalRow = $lastRow + 1;

                foreach (self::COLUMNS as $key => [, $width]) {
                    $sheet->getColumnDimension($this->col[$key])->setWidth($width);
                }

                // Header
                $sheet->getRowDimension(1)->setRowHeight(34);
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'underline' => true, 'size' => 10],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => self::HEADER_FILL]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->freezePane('A2');

                if ($lastRow > 1) {
                    $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");

                    $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
                        'font' => ['size' => 10],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A2:A{$lastRow}")->getNumberFormat()->setFormatCode('d mmm yyyy');
                    $sheet->getStyle("{$this->col['rate']}2:{$this->col['rate']}{$lastRow}")->getNumberFormat()->setFormatCode('0.00%');
                    $sheet->getStyle("{$this->col['broker_pct']}2:{$this->col['broker_pct']}{$lastRow}")->getNumberFormat()->setFormatCode('0.00%');
                    // Phones stay text (no 9.71E+11).
                    $sheet->getStyle("{$this->col['buyer_phone']}2:{$this->col['buyer_phone']}{$lastRow}")->getNumberFormat()->setFormatCode('@');
                    $sheet->getStyle("{$this->col['spa']}2:{$this->col['spa']}{$lastRow}")->getFont()->setBold(true)->getColor()->setRGB('15803D');
                }

                // Money columns: number format, right-aligned (data + totals).
                foreach (self::MONEY_KEYS as $key) {
                    $range = "{$this->col[$key]}2:{$this->col[$key]}{$totalRow}";
                    $sheet->getStyle($range)->getNumberFormat()->setFormatCode(self::MONEY);
                    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Highlight Commission After Referral like the original workbook.
                if ($lastRow > 1) {
                    $sheet->getStyle("{$this->col['after_referral']}2:{$this->col['after_referral']}{$lastRow}")
                        ->getFill()->setFillType('solid')->getStartColor()->setRGB('D9F2D0');
                }

                // Totals row
                if ($lastRow > 1) {
                    $sheet->setCellValue("A{$totalRow}", 'Total');
                    foreach (self::TOTAL_KEYS as $key) {
                        $l = $this->col[$key];
                        $sheet->setCellValue("{$l}{$totalRow}", "=SUM({$l}2:{$l}{$lastRow})");
                    }
                    $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => self::TOTAL_FILL]],
                    ]);
                }

                // Grid borders on header + data + totals.
                $sheet->getStyle("A1:{$lastCol}".max(1, $lastRow > 1 ? $totalRow : 1))->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('7F7F7F');
            },
        ];
    }
}
