<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@crm.test'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'), 
            ]
        );

        $role = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($role);

        $this->command->info('✅ Admin user created successfully!');
    }
}
