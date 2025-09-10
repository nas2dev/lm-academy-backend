<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'assign_permissions',
                // 'description' => 'Can assign permissions',
            ],
            [
                'name' => 'revoke_permissions',
                // 'description' => 'Can revoke permissions',
            ],
            [
                'name' => 'enable_account',
                // 'description' => 'Can enable user accounts',
            ],
            [
                'name' => 'disable_account',
                // 'description' => 'Can disable user accounts',
            ],
            [
                'name' => 'can_create_course',
                // 'description' => 'Can create a new course',
            ],
            [
                'name' => 'edit_course_materials',
                // 'description' => 'Can edit materials in a course',
            ],
            [
                'name' => 'view_course_materials',
                // 'description' => 'Can view materials in a course',
            ],
        ];

        // Insert the permissions into the database
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}
