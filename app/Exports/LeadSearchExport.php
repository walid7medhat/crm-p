<?php

namespace App\Exports;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * "Export Leads" page (super_admin): leads whose lead name, first / last name, source,
 * referral client name, source information or comment contain the search text — as
 * Excel (Lead Name, Name, Email). Read in chunks (FromQuery), so big results don't
 * load into memory at once.
 */
class LeadSearchExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /** Columns the search text is matched against. */
    public const SEARCH_COLUMNS = [
        'lead_name',
        'first_name',
        'last_name',
        'lead_source',
        'source_client_name',
        'source_information',
        'comment',
    ];

    public function __construct(private string $term)
    {
    }

    /** Shared by the page preview and the Excel download. Newest first. */
    public static function searchQuery(string $term): Builder
    {
        $like = '%' . addcslashes(mb_strtolower(trim($term)), '%_\\') . '%';

        return Lead::query()
            ->select(['id', 'lead_name', 'first_name', 'last_name', 'email'])
            ->where(function (Builder $q) use ($like) {
                foreach (self::SEARCH_COLUMNS as $column) {
                    $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                }
            })
            ->orderByDesc('id');
    }

    public static function fullName($lead): string
    {
        return trim(trim((string) $lead->first_name) . ' ' . trim((string) $lead->last_name));
    }

    public function query()
    {
        return self::searchQuery($this->term);
    }

    public function headings(): array
    {
        return ['Lead Name', 'Name', 'Email'];
    }

    public function map($lead): array
    {
        return [
            (string) $lead->lead_name,
            self::fullName($lead),
            (string) $lead->email,
        ];
    }
}
