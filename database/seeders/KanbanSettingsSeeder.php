<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KanbanSettingsSeeder extends Seeder
{
    public function run()
    {
        // Card fields settings
        DB::table('kanban_settings')->updateOrInsert(
            ['key' => 'card_fields'],
            [
                'value' => json_encode([
                    [
                        'key' => 'lead_name',
                        'label' => 'Lead Name',
                        'enabled' => true,
                        'order' => 1
                    ],
                    [
                        'key' => 'created_by',
                        'label' => 'Created By',
                        'enabled' => true,
                        'order' => 2
                    ],
                    [
                        'key' => 'created_at',
                        'label' => 'Date',
                        'enabled' => true,
                        'order' => 3
                    ],
                    [
                        'key' => 'responsible_person',
                        'label' => 'Responsible',
                        'enabled' => true,
                        'order' => 4
                    ],
                    [
                        'key' => 'assigned_by',
                        'label' => 'Assigned By',
                        'enabled' => true,
                        'order' => 5
                    ],
                    [
                        'key' => 'lead_source',
                        'label' => 'Source',
                        'enabled' => true,
                        'order' => 6
                    ],
                    [
                        'key' => 'lead_branch_source',
                        'label' => 'Branch Source',
                        'enabled' => false,
                        'order' => 7
                    ],
                    [
                        'key' => 'first_name',
                        'label' => 'First Name',
                        'enabled' => false,
                        'order' => 8
                    ],
                     [
                        'key' => 'last_name',
                        'label' => 'Last Name',
                        'enabled' => false,
                        'order' => 8
                    ],[
                        'key' => 'work_phone',
                        'label' => 'Phone',
                        'enabled' => false,
                        'order' => 8
                    ],
                    [
                        'key' => 'email',
                        'label' => 'Email',
                        'enabled' => false,
                        'order' => 9
                    ],
                    [
                        'key' => 'duplicate_count',
                        'label' => 'Duplicates',
                        'enabled' => false,
                        'order' => 10
                    ],
                    [
                        'key' => 'bedrooms',
                        'label' => 'Bedrooms',
                        'enabled' => false,
                        'order' => 11
                    ],
                    [
                        'key' => 'budget',
                        'label' => 'Budget',
                        'enabled' => false,
                        'order' => 12
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Revert hours setting (single number)
        DB::table('kanban_settings')->updateOrInsert(
            ['key' => 'revert_hours'],
            [
                'value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}