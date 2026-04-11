<?php

namespace App\Imports;

use App\Models\Lead;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\DefaultValueBinder;

class LeadsImport extends DefaultValueBinder implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithCustomValueBinder
{
    private $stages;
    private $users;
    private $start;
    private $end;
    private $currentRow = 0;

    private $errors = [];

    public function __construct($start = 1, $end = 100000)
    {
        $this->stages = Stage::select('id', 'name')->get();
        $this->users  = User::select('id', 'name')->get();

        $this->start = $start;
        $this->end = $end;
    }

    public function chunkSize(): int
    {
        return 500;
    }

   

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            $this->currentRow++;

            try {

                if ($this->currentRow < $this->start) continue;
                if ($this->currentRow > $this->end) return;

                $data = $this->normalize($row->toArray());

                if (empty($data['lead_name'])) {
                    continue;
                }

                $stage  = $this->resolveStage($data['stage'] ?? null);
                $userId = $this->resolveUser($data['responsible'] ?? null);

                $phone = $data['work_phone'] ?? null;

                $email = $data['work_e_mail']
                    ?? $data['home_e_mail']
                    ?? $data['other_e_mail']
                    ?? null;

                $firstName = $data['first_name']
                    ?? $data['name']
                    ?? null;

                if (!$firstName && !empty($data['lead_name'])) {
                    $firstName = explode(' ', $data['lead_name'])[0];
                }

                if (!$firstName) {
                    throw new \Exception("Missing first_name / lead_name empty");
                }

                $addedBy = $this->resolveUser($data['created_by'] ?? null);

                Lead::create([
                    'email' => $email,
                    'added_by' => $addedBy ?? 1,

                    'lead_name' => $data['lead_name'] ?? 'Imported Lead',
                    'lead_number' => $data['id'] ?? null,

                    'stage_id' => $stage?->id,
                    'last_stage_change_at' => now(),

                    'salutation' => $data['salutation'] ?? null,
                    'first_name' => $firstName,
                    'second_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,

                    'date_of_birth' => $this->parseDate($data['date_of_birth'] ?? null),

                    'whatsapp_number' => $data['whatsapp_number'] ?? null,
                    'work_phone' =>  $data['work_phone']
                        ?? null,
                    'work_phone_2' => $data['work_phone_2']
                        ?? $data['other_phone_number']
                        ?? null,

                    'secondary_email' => $data['home_e_mail'] ?? null,

                    'website' => $data['corporate_website'] ?? $data['personal_page'] ?? null,
                    'facebook' => $data['facebook_page'] ?? null,
                    'messenger' => $data['telegram_account'] ?? null,

                    'company_name' => $data['company_name'] ?? null,
                    'position' => $data['position'] ?? null,

                    'interested_in' => $data['intrested_in'] ?? null,
                    'bedrooms' => $data['bedrooms'] ?? null,
                    'purpose_buying' => $data['purpose_you_are_looking_to_purchase'] ?? null,

                    'nationality' => $data['nationality'] ?? null,

                    'lead_source' => $data['source'] ?? 'Excel Import',
                    'source_information' => $data['source_information'] ?? null,

                    'responsible_person_id' => $userId,
                    'initial_responsible_person_id' => $userId,

                    'created_at' => now(),
                    'updated_at' => now(),

                    'raw_meta_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);

            } catch (\Exception $e) {

                $this->errors[] = [
                    'row' => $this->currentRow,
                    'error' => $e->getMessage(),
                    'data' => $row->toArray()
                ];

                continue;
            }
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

        private function normalize($data)
    {
        $normalized = [];

        foreach ($data as $key => $value) {

            $key = strtolower((string) $key);

            $key = str_replace(
                [' ', '-', '?', '.', '/'],
                '_',
                $key
            );

            $key = trim($key);

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function resolveStage($name)
    {
        if (!$name) return $this->stages->first();

        foreach ($this->stages as $stage) {
            if (str_contains(strtolower($stage->name), strtolower($name))) {
                return $stage;
            }
        }

        return $this->stages->first();
    }

    private function resolveUser($name)
    {
        if (!$name) return auth()->id() ?? 1;

        $name = strtolower($name);

        foreach ($this->users as $user) {

            $userName = strtolower($user->name);

            if (
                str_contains($name, $userName) ||
                str_contains($userName, $name)
            ) {
                return $user->id;
            }
        }

        return auth()->id() ?? 1;
    }

    private function parseDate($date)
    {
        try {
            return $date ? Carbon::parse($date) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}