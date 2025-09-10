<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Retrieve the admin and consultant roles
        $adminRole = Role::where('name', 'Admin')->first();
        $userRole = Role::where('name', 'User')->first();


        // Retrieve all permissions that belogs to roles and not specific user
        // $permissions = Permission::all();
        $permissions = Permission::whereDoesntHave('users')->get();

        // Assign permissions to roles
        $editCourseMaterialsPermission = Permission::where('name', 'edit_course_materials')->first();
        $createCoursePermission = Permission::where('name', 'create_course')->first();
        $viewCourseMaterialsPermission = Permission::where('name', 'view_course_materials')->first();

        // Assign permissions to roles
        $adminRole->syncPermissions($permissions); //all permissions to admin role
        $userRole->syncPermissions([
            $editCourseMaterialsPermission, $createCoursePermission, $viewCourseMaterialsPermission
        ]);

    }
}
