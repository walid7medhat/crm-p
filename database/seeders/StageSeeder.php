<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Stage;

class StageSeeder extends Seeder
{
    /**
     * Seed default lead stages and visibility.
     */
    public function run(): void
    {
        // Basic lead stages pipeline
        $stages = [
            ['name' => 'New',        'order' => 1],
            ['name' => 'Contacted',  'order' => 2],
            ['name' => 'Qualified',  'order' => 3],
            ['name' => 'Proposal',   'order' => 4],
            ['name' => 'Negotiation','order' => 5],
            ['name' => 'Converted',  'order' => 6],
            ['name' => 'Lost',       'order' => 7],
        ];

        $ids = [];

        foreach ($stages as $data) {
            $stage = Stage::firstOrCreate(
                [
                    'name'       => $data['name'],
                    'stage_type' => 'lead',
                ],
                [
                    'order'      => $data['order'],
                    'deal_type'  => null,
                    'added_by'   => null,
                ]
            );

            $ids[] = $stage->id;
        }

        // If stage_visibility table exists, seed default visibility
        if (DB::getSchemaBuilder()->hasTable('stage_visibility') && !empty($ids)) {
            $roles = ['super_admin', 'admin', 'manager', 'team_lead', 'sales', 'marketing'];

            foreach ($roles as $role) {
                DB::table('stage_visibility')->updateOrInsert(
                    ['role_name' => $role],
                    ['visible_stages' => json_encode($ids)]
                );
            }
        }
    }
}

