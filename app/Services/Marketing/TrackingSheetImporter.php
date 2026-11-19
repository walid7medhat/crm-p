<?php

namespace App\Services\Marketing;

use App\Models\MarketingTrackingEntry;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports the legacy Blog / OIA / All Properties tracking workbooks into
 * marketing_tracking_entries (preserving hyperlinks).
 */
class TrackingSheetImporter
{
    public function importAll(bool $wipe = true): array
    {
        $base = database_path('data/tracking-sheets');
        $files = [
            'blogs' => $base . '/blogs.xlsx',
            'oia' => $base . '/oia.xlsx',
            'all' => $base . '/all.xlsx',
        ];

        foreach ($files as $path) {
            if (! is_file($path)) {
                throw new \RuntimeException("Missing import file: {$path}");
            }
        }

        return DB::transaction(function () use ($files, $wipe) {
            if ($wipe) {
                MarketingTrackingEntry::query()->forceDelete();
            }

            $counts = [
                'blogs' => $this->importBlogs($files['blogs']),
                'oia' => $this->importLanding($files['oia'], 'oia'),
                'all' => $this->importLanding($files['all'], 'all'),
            ];

            return $counts;
        });
    }

    protected function importBlogs(string $path): int
    {
        $wb = $this->loadWorkbook($path);
        $ws = $wb->getActiveSheet();
        $rows = $ws->toArray(null, true, true, false);
        $count = 0;
        $order = 0;

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue; // header
            }

            $name = $this->clean($row[0] ?? null);
            $contentLabel = $this->clean($row[1] ?? null);
            if ($name === '' && $contentLabel === '') {
                continue;
            }

            $publishedRaw = strtoupper(trim((string) ($row[2] ?? '')));
            $isPublished = in_array($publishedRaw, ['TRUE', '1', 'YES'], true)
                ? true
                : (in_array($publishedRaw, ['FALSE', '0', 'NO'], true) ? false : null);

            $excelRow = $i + 1;
            MarketingTrackingEntry::create([
                'category' => 'blogs',
                'sheet_group' => 'blogs',
                'region' => null,
                'name' => $name !== '' ? $name : ($contentLabel !== '' ? $contentLabel : 'Untitled blog'),
                'description' => null,
                'is_published' => $isPublished,
                'content_label' => $contentLabel !== '' ? $contentLabel : null,
                'content_url' => $this->hyperlink($ws, 'B', $excelRow),
                'page_label' => $this->clean($row[3] ?? null) ?: null,
                'page_url' => $this->hyperlink($ws, 'D', $excelRow),
                'linkedin_label' => $this->clean($row[4] ?? null) ?: null,
                'linkedin_url' => $this->hyperlink($ws, 'E', $excelRow),
                'instagram_label' => $this->clean($row[5] ?? null) ?: null,
                'instagram_url' => $this->hyperlink($ws, 'F', $excelRow),
                'sort_order' => $order++,
            ]);
            $count++;
        }

        return $count;
    }

    protected function importLanding(string $path, string $category): int
    {
        $wb = $this->loadWorkbook($path);
        $count = 0;

        foreach ($wb->getWorksheetIterator() as $ws) {
            $title = strtolower(trim($ws->getTitle()));
            $group = $this->resolveGroup($title);
            if (! $group) {
                continue;
            }

            $count += $this->importLandingSheet($ws, $category, $group);
        }

        return $count;
    }

    protected function resolveGroup(string $title): ?string
    {
        if (str_contains($title, 'devel')) {
            return 'developers';
        }
        if (str_contains($title, 'domain')) {
            return 'new_domains';
        }
        if (str_contains($title, 'project') || $title === 'projects' || str_contains($title, 'dubai')) {
            return 'projects';
        }

        return null;
    }

    protected function importLandingSheet(Worksheet $ws, string $category, string $group): int
    {
        $rows = $ws->toArray(null, true, true, false);
        $region = null;
        $order = 0;
        $count = 0;
        $headerSeen = false;

        foreach ($rows as $i => $row) {
            $excelRow = $i + 1;
            $cells = array_map(fn ($v) => $this->clean($v), array_slice($row, 0, 6));
            $nonEmpty = array_values(array_filter($cells, fn ($v) => $v !== ''));

            if (! $nonEmpty) {
                continue;
            }

            // Section banners like "ABU Dhabi" / "DUBAI"
            if (count($nonEmpty) === 1) {
                $banner = strtolower($nonEmpty[0]);
                if (str_contains($banner, 'abu') && str_contains($banner, 'dhabi')) {
                    $region = 'abu_dhabi';
                    continue;
                }
                if (str_contains($banner, 'dubai')) {
                    $region = 'dubai';
                    continue;
                }
                // Title-only rows / "Developer" separators
                if (
                    str_contains($banner, 'website')
                    || str_contains($banner, 'projects')
                    || $banner === 'developer'
                    || str_contains($banner, 'domain')
                ) {
                    continue;
                }
            }

            // Skip header rows
            $joined = strtolower(implode(' ', $nonEmpty));
            if (
                ! $headerSeen
                && (
                    str_contains($joined, 'name')
                    && (str_contains($joined, 'link') || str_contains($joined, 'content') || str_contains($joined, 'area') || str_contains($joined, 'developer'))
                )
            ) {
                $headerSeen = true;
                continue;
            }
            if (
                str_contains($joined, 'name of the project')
                || ($cells[0] === 'NAME' || $cells[0] === 'Name')
            ) {
                $headerSeen = true;
                continue;
            }

            if ($group === 'developers') {
                $name = $cells[0] !== '' ? $cells[0] : null;
                $contentLabel = $cells[1] !== '' ? $cells[1] : null;
                if (! $name && ! $contentLabel) {
                    continue;
                }

                MarketingTrackingEntry::create([
                    'category' => $category,
                    'sheet_group' => $group,
                    'region' => $region,
                    'name' => $name ?: $contentLabel,
                    'description' => null,
                    'content_label' => $contentLabel,
                    'content_url' => $this->hyperlink($ws, 'B', $excelRow) ?: $this->hyperlink($ws, 'A', $excelRow),
                    'sort_order' => $order++,
                ]);
                $count++;
                continue;
            }

            if ($group === 'new_domains') {
                $name = $cells[0] !== '' ? $cells[0] : null;
                $contentLabel = $cells[1] !== '' ? $cells[1] : null;
                $doneBy = $cells[2] !== '' ? $cells[2] : null;
                $pageUrlText = $cells[3] !== '' ? $cells[3] : null;
                $notes = $cells[4] !== '' ? $cells[4] : null;
                if (! $name && ! $contentLabel) {
                    continue;
                }

                $pageUrl = $this->hyperlink($ws, 'D', $excelRow);
                if (! $pageUrl && $pageUrlText && filter_var($pageUrlText, FILTER_VALIDATE_URL)) {
                    $pageUrl = $pageUrlText;
                }

                MarketingTrackingEntry::create([
                    'category' => $category,
                    'sheet_group' => $group,
                    'region' => $region,
                    'name' => $name ?: $contentLabel,
                    'description' => $notes,
                    'done_by' => $doneBy,
                    'content_label' => $contentLabel,
                    'content_url' => $this->hyperlink($ws, 'B', $excelRow),
                    'page_label' => $pageUrlText && ! filter_var($pageUrlText, FILTER_VALIDATE_URL) ? $pageUrlText : null,
                    'page_url' => $pageUrl,
                    'sort_order' => $order++,
                ]);
                $count++;
                continue;
            }

            // projects (default)
            $name = $cells[0] !== '' ? $cells[0] : null;
            $area = $cells[1] !== '' ? $cells[1] : null;
            $developer = $cells[2] !== '' ? $cells[2] : null;
            $contentLabel = $cells[3] !== '' ? $cells[3] : null;
            $pageLabel = $cells[4] !== '' ? $cells[4] : null;
            $notes = $cells[5] !== '' ? $cells[5] : null;

            // Some Dubai rows: Name, Content, done by, link
            if ($group === 'projects' && $area && ! $developer && ! $contentLabel && filter_var($developer ?? '', FILTER_VALIDATE_URL) === false) {
                // keep as-is
            }

            if (! $name && ! $contentLabel && ! $pageLabel) {
                continue;
            }

            // Detect alternate Dubai header layout: Name / Content / done by / link
            // When "Area" looks like a content title and Developer looks like a person name with a URL in col D
            $pageUrl = $this->hyperlink($ws, 'E', $excelRow) ?: $this->hyperlink($ws, 'D', $excelRow);
            $contentUrl = $this->hyperlink($ws, 'D', $excelRow);
            // Prefer column D as content when E also has a link (OIA/All projects layout)
            if ($this->hyperlink($ws, 'E', $excelRow)) {
                $contentUrl = $this->hyperlink($ws, 'D', $excelRow);
                $pageUrl = $this->hyperlink($ws, 'E', $excelRow);
            } elseif ($pageUrl && ! $contentUrl) {
                // new-domain-like project rows with URL in D
                $contentUrl = $this->hyperlink($ws, 'B', $excelRow);
            }

            // Plain URL sitting in page label column
            if (! $pageUrl && $pageLabel && filter_var($pageLabel, FILTER_VALIDATE_URL)) {
                $pageUrl = $pageLabel;
                $pageLabel = null;
            }
            if (! $pageUrl && $contentLabel && filter_var($contentLabel, FILTER_VALIDATE_URL) && ! $contentUrl) {
                $contentUrl = $contentLabel;
                $contentLabel = null;
            }

            MarketingTrackingEntry::create([
                'category' => $category,
                'sheet_group' => 'projects',
                'region' => $region,
                'name' => $name ?: ($contentLabel ?: ($pageLabel ?: 'Untitled')),
                'description' => $notes,
                'area' => $area,
                'developer' => $developer,
                'content_label' => $contentLabel,
                'content_url' => $contentUrl,
                'page_label' => $pageLabel,
                'page_url' => $pageUrl,
                'sort_order' => $order++,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * PhpSpreadsheet emits an unsuppressed simplexml warning for empty optional
     * parts inside some workbooks (Reader/Xlsx::loadZipNonamespace). The load
     * succeeds, but Laravel promotes warnings to exceptions, so swallow just those.
     */
    protected function loadWorkbook(string $path): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        set_error_handler(
            fn (int $errno, string $errstr) => str_starts_with($errstr, 'simplexml_load_string()'),
            E_WARNING
        );

        try {
            return IOFactory::load($path);
        } finally {
            restore_error_handler();
        }
    }

    protected function hyperlink(Worksheet $ws, string $col, int $row): ?string
    {
        $coord = $col . $row;
        $url = trim((string) $ws->getHyperlink($coord)->getUrl());
        if ($url === '') {
            // Sometimes the cell value itself is a URL
            $val = trim((string) $ws->getCell($coord)->getCalculatedValue());
            if ($val !== '' && filter_var($val, FILTER_VALIDATE_URL)) {
                return $val;
            }

            return null;
        }

        return $url;
    }

    protected function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }
}
