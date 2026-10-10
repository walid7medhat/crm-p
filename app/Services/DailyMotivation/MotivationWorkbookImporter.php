<?php

namespace App\Services\DailyMotivation;

use App\Models\MotivationMessage;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class MotivationWorkbookImporter
{
    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Workbook not found at '.$path);
        }

        $rows = $this->readRows($path);
        $parsed = $this->parse($rows);

        return DB::transaction(function () use ($parsed) {
            $created = 0;
            $updated = 0;

            foreach ($parsed as $row) {
                $existing = MotivationMessage::query()->where('number', $row['number'])->first();
                MotivationMessage::query()->updateOrCreate(
                    ['number' => $row['number']],
                    [
                        'body_en' => $row['body_en'],
                        'body_ar' => $row['body_ar'],
                        'subtitle_en' => $row['subtitle_en'],
                        'subtitle_ar' => $row['subtitle_ar'],
                    ]
                );

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'total' => count($parsed),
            ];
        });
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array{number: int, body_en: string, body_ar: string, subtitle_en: ?string, subtitle_ar: ?string}>
     */
    public function parse(array $rows): array
    {
        $rows = array_values(array_filter($rows, function ($row) {
            foreach ((array) $row as $cell) {
                if ($this->clean($cell) !== '') {
                    return true;
                }
            }

            return false;
        }));

        if ($rows === []) {
            throw new RuntimeException('The workbook has no rows.');
        }

        $headerIndex = $this->headerIndex($rows);
        $map = null;
        $dataRows = $rows;

        if ($headerIndex !== null) {
            $map = $this->mapHeaders($rows[$headerIndex]);
            $dataRows = array_slice($rows, $headerIndex + 1);
        }

        $parsed = [];
        $sequential = 0;

        foreach ($dataRows as $row) {
            $row = array_values((array) $row);
            $sequential++;

            if ($map) {
                $numberCell = $map['number'] === null ? null : ($row[$map['number']] ?? null);
                $english = $this->clean($row[$map['english']] ?? null);
                $arabic = $this->clean($row[$map['arabic']] ?? null);
                $subtitleEn = $map['subtitle_en'] === null ? null : $this->nullable($row[$map['subtitle_en']] ?? null);
                $subtitleAr = $map['subtitle_ar'] === null ? null : $this->nullable($row[$map['subtitle_ar']] ?? null);
            } else {
                $numberCell = $row[0] ?? null;
                $english = $this->clean($row[1] ?? null);
                $arabic = $this->clean($row[2] ?? null);
                $subtitleEn = $this->nullable($row[3] ?? null);
                $subtitleAr = $this->nullable($row[4] ?? null);
            }

            if ($english === '' && $arabic === '' && $this->clean($numberCell) === '') {
                continue;
            }

            $number = $this->numberValue($numberCell);
            if ($number === null) {
                $number = $sequential;
            }

            $parsed[] = [
                'number' => $number,
                'body_en' => $english,
                'body_ar' => $arabic,
                'subtitle_en' => $subtitleEn,
                'subtitle_ar' => $subtitleAr,
            ];
        }

        $this->assertComplete($parsed);

        return $parsed;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function headerIndex(array $rows): ?int
    {
        $limit = min(8, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $cells = array_map(fn ($cell) => $this->headerKey($cell), (array) $rows[$i]);
            $hasEnglish = false;
            $hasArabic = false;
            foreach ($cells as $key) {
                if ($key === '') {
                    continue;
                }
                if ($this->isArabicHeader($key)) {
                    $hasArabic = true;
                } elseif ($this->isEnglishHeader($key)) {
                    $hasEnglish = true;
                }
            }
            if ($hasEnglish && $hasArabic) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array{number: int, english: int, arabic: int, subtitle_en: ?int, subtitle_ar: ?int}
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [
            'number' => null,
            'english' => null,
            'arabic' => null,
            'subtitle_en' => null,
            'subtitle_ar' => null,
        ];

        foreach (array_values($headerRow) as $index => $cell) {
            $key = $this->headerKey($cell);
            if ($key === '') {
                continue;
            }
            if ($map['arabic'] === null && $this->isArabicHeader($key)) {
                $map['arabic'] = $index;
                continue;
            }
            if ($map['subtitle_ar'] === null && (str_contains($key, 'subtitle ar') || str_contains($key, 'الاجراء') || str_contains($key, 'الإجراء'))) {
                $map['subtitle_ar'] = $index;
                continue;
            }
            if ($map['number'] === null && $this->isNumberHeader($key)) {
                $map['number'] = $index;
                continue;
            }
            if ($map['subtitle_en'] === null && (str_contains($key, 'subtitle') || str_contains($key, 'daily action') || $key === 'action')) {
                $map['subtitle_en'] = $index;
                continue;
            }
            if ($map['english'] === null && $this->isEnglishHeader($key)) {
                $map['english'] = $index;
            }
        }

        if ($map['english'] === null || $map['arabic'] === null) {
            throw new RuntimeException('The workbook needs an English column and an Arabic column.');
        }

        return $map;
    }

    /**
     * @param  array<int, array{number: int, body_en: string, body_ar: string}>  $parsed
     */
    private function assertComplete(array $parsed): void
    {
        if (count($parsed) !== MotivationRotation::CYCLE) {
            throw new RuntimeException(
                'Expected exactly '.MotivationRotation::CYCLE.' messages, found '.count($parsed).'. No rows were saved.'
            );
        }

        $seen = [];
        $missingText = [];

        foreach ($parsed as $row) {
            $number = $row['number'];
            if ($number < 1 || $number > MotivationRotation::CYCLE) {
                throw new RuntimeException("Message number {$number} is outside 1–".MotivationRotation::CYCLE.'. No rows were saved.');
            }
            if (isset($seen[$number])) {
                throw new RuntimeException("Message number {$number} is duplicated. No rows were saved.");
            }
            $seen[$number] = true;
            if ($row['body_en'] === '' || $row['body_ar'] === '') {
                $missingText[] = $number;
            }
        }

        $missingNumbers = [];
        for ($n = 1; $n <= MotivationRotation::CYCLE; $n++) {
            if (! isset($seen[$n])) {
                $missingNumbers[] = $n;
            }
        }

        if ($missingNumbers !== []) {
            throw new RuntimeException(
                'Missing message numbers: '.implode(', ', $missingNumbers).'. No rows were saved.'
            );
        }

        if ($missingText !== []) {
            throw new RuntimeException(
                'English and Arabic are both required. Empty text on numbers: '.implode(', ', $missingText).'. No rows were saved.'
            );
        }
    }

    private function isNumberHeader(string $key): bool
    {
        return in_array($key, ['number', 'no', 'no.', '#', 'id', 'msg', 'message no', 'message number', 'رقم'], true);
    }

    private function isEnglishHeader(string $key): bool
    {
        if ($this->isArabicHeader($key) || $this->isNumberHeader($key)) {
            return false;
        }

        return str_contains($key, 'english')
            || str_contains($key, 'motivation')
            || $key === 'en'
            || $key === 'message'
            || $key === 'quote'
            || $key === 'text';
    }

    private function isArabicHeader(string $key): bool
    {
        return str_contains($key, 'arab')
            || str_contains($key, 'عرب')
            || str_contains($key, 'ترجم')
            || $key === 'ar';
    }

    private function headerKey(mixed $value): string
    {
        $value = $this->clean($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function numberValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && abs($value - (int) $value) < 0.001) {
            return (int) $value;
        }

        $clean = $this->clean($value);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        $number = (float) $clean;
        if (abs($number - (int) $number) > 0.001) {
            return null;
        }

        return (int) $number;
    }

    private function nullable(mixed $value): ?string
    {
        $clean = $this->clean($value);

        return $clean === '' ? null : $clean;
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = str_replace("\xc2\xa0", ' ', (string) $value);

        return trim($text);
    }
}
