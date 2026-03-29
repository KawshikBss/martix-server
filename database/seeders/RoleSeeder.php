<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::insert([
            ['name' => 'owner', 'is_system_role' => false],
            ['name' => 'manager', 'is_system_role' => false],
            ['name' => 'staff', 'is_system_role' => false],

            ['name' => 'admin', 'is_system_role' => true],
            ['name' => 'moderator', 'is_system_role' => true],
        ]);
    }
}
