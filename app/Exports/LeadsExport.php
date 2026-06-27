<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromCollection;
class LeadsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Lead::select(
            'id',
            'bitrix24_id',
            'lead_name',
            'first_name',
            'work_phone'
        )        ->limit(500)->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Bitrix ID',
            'Lead Name',
            'First Name',
            'Work Phone',
        ];
    }
}
