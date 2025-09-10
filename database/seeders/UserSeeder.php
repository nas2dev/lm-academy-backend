<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $albanianUsers = [
            [
                'user' => [
                    'first_name' => 'Arben',
                    'last_name' => 'Hoxha',
                    'gender' => 'male',
                    'email' => 'arben.hoxha@example.com',
                    'date_of_birth' => '1995-03-15',
                    'academic_year' => 3,
                    'password' => Hash::make('password123'),
                    'profile_completed' => true,
                ],
                'info' => [
                    'address' => 'Rruga Adem Jashari, Vushtrri',
                    'tel' => '+383 44 123 456',
                    'about' => 'Computer Science student with a passion for web development.',
                ],
                'role' => 'Admin'
            ],
            [
                'user' => [
                    'first_name' => 'Enkeleda',
                    'last_name' => 'Shehu',
                    'gender' => 'female',
                    'email' => 'enkeleda.shehu@example.com',
                    'date_of_birth' => '1998-07-22',
                    'academic_year' => 2,
                    'password' => Hash::make('password123'),
                    'profile_completed' => true,
                ],
                'info' => [
                    'address' => 'Rruga UCK, Prishtinë',
                    'tel' => '+383 45 234 567',
                    'about' => 'Studying Information Technology with focus on cybersecurity.',
                ],
                'role' => 'Admin'
            ],
            [
                'user' => [
                    'first_name' => 'Dritan',
                    'last_name' => 'Prifti',
                    'gender' => 'male',
                    'email' => 'dritan.prifti@example.com',
                    'date_of_birth' => '1997-11-30',
                    'academic_year' => 4,
                    'password' => Hash::make('password123'),
                    'profile_completed' => true,
                ],
                'info' => [
                    'address' => 'Rruga Skenderbeu, Vushtrri',
                    'tel' => '+383 49 345 678',
                    'about' => 'Final year student specializing in artificial intelligence.',
                ],
                'role' => 'User'
            ],
            [
                'user' => [
                    'first_name' => 'Blerina',
                    'last_name' => 'Kola',
                    'gender' => 'female',
                    'email' => 'blerina.kola@example.com',
                    'date_of_birth' => '1999-05-18',
                    'academic_year' => 1,
                    'password' => Hash::make('password123'),
                    'profile_completed' => true,
                ],
                'info' => [
                    'address' => 'Rruga Mbreteresha Teutë, Mitrovicë',
                    'tel' => '+383 44 456 789',
                    'about' => 'Freshman student interested in mobile app development.',
                ],
                'role' => 'User'
            ],
            [
                'user' => [
                    'first_name' => 'Genti',
                    'last_name' => 'Brahimi',
                    'gender' => 'male',
                    'email' => 'genti.brahimi@example.com',
                    'date_of_birth' => '1996-09-25',
                    'academic_year' => 3,
                    'password' => Hash::make('password123'),
                    'profile_completed' => true,
                ],
                'info' => [
                    'address' => 'Rruga Dëshmorët e Kombit, Pejë',
                    'tel' => '+383 45 567 890',
                    'about' => 'Software engineering student focusing on cloud computing.',
                ],
                'role' => 'User'
            ],
        ];

        foreach ($albanianUsers as $key => $userData) {
            $user = User::create($userData['user']);
            $user->UserInfo()->create($userData['info']);
            $user->assignRole($userData['role'] ?? 'User');

            if ($key === 0) {
                $permissions = [
                    [
                        'name' => 'create_permissions',
                        // 'description' => 'Can create new permissions',
                    ],
                    [
                        'name' => 'update_permissions',
                        // 'description' => 'Can update permissions',
                    ],
                    [
                        'name' => 'delete_permissions',
                        // 'description' => 'Can delete permissions',
                    ],
                    [
                        'name' => 'update_admin_profile_permission',
                        // 'description' => 'Can update admin profile permission',
                    ],
                    [
                        'name' => 'reset_admin_password_permission',
                        // 'description' => 'Can reset admin password permission',
                    ],
                ];

                $createdPermissions = [];
                foreach ($permissions as $permission) {
                    $createdPermissions[] = Permission::create($permission);
                }

                $user->givePermissionTo($createdPermissions);
            }
        }
    }
}
