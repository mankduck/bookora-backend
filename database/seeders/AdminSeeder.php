<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            [
                'email' => 'admin@bookora.test',
            ],
            [
                'name' => 'Bookora Admin',
                'phone' => '0900000001',
                'password' => Hash::make('Admin@123456'),
                'status' => 'active',
            ]
        );

        $adminRole = Role::where('code', 'admin')->firstOrFail();

        $admin->roles()->syncWithoutDetaching([
            $adminRole->id,
        ]);
    }
}