<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'code' => 'admin',
                'description' => 'Full system access',
            ],
            [
                'name' => 'Staff',
                'code' => 'staff',
                'description' => 'Staff access',
            ],
            [
                'name' => 'Customer',
                'code' => 'customer',
                'description' => 'Customer access',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}