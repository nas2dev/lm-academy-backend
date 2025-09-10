<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseSectionSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $sections = [
            // Module 1: Introduction to Laravel (200 minutes total)
            [
                'module_id' => 1,
                'title' => 'Setting Up Your Laravel Environment',
                'description' => 'In this section, participants will learn how to install and configure their Laravel development environment, including required tools and dependencies.',
                'duration' => 45 * 60,
                'nr_of_files' => 14,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 1,
                'title' => 'Understanding Laravel\'s Architecture',
                'description' => 'This section introduces the architectural foundations of Laravel, including its directory structure and core concepts.',
                'duration' => 60 * 60,
                'nr_of_files' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 1,
                'title' => 'Introduction to Blade Templating',
                'description' => 'This section introduces Laravel\'s Blade templating engine and its powerful features for building dynamic views.',
                'duration' => 95 * 60,
                'nr_of_files' => 112,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Module 2: Routes and Views (200 minutes total)
            [
                'module_id' => 2,
                'title' => 'Basic Routing',
                'description' => 'Learn how to define basic routes and handle different HTTP methods in Laravel.',
                'duration' => 70 * 60,
                'nr_of_files' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 2,
                'title' => 'Route Parameters and Constraints',
                'description' => 'Understanding route parameters, optional parameters, and route constraints for building flexible URLs.',
                'duration' => 65 * 60,
                'nr_of_files' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 2,
                'title' => 'View Composition',
                'description' => 'Creating and managing views, passing data to views, and using view composers.',
                'duration' => 65 * 60,
                'nr_of_files' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Module 3: Controllers and MVC Design Pattern (200 minutes total)
            [
                'module_id' => 3,
                'title' => 'MVC Architecture Overview',
                'description' => 'Understanding the Model-View-Controller pattern and its implementation in Laravel.',
                'duration' => 60 * 60,
                'nr_of_files' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 3,
                'title' => 'Controller Fundamentals',
                'description' => 'Creating and organizing controllers, handling requests, and implementing controller logic.',
                'duration' => 70 * 60,
                'nr_of_files' => 25,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 3,
                'title' => 'Resource Controllers and API Development',
                'description' => 'Working with resource controllers and building RESTful APIs in Laravel.',
                'duration' => 70 * 60,
                'nr_of_files' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Keeping existing Vue.js sections (module_id: 4)
            [
                'module_id' => 4,
                'title' => 'Component Communication Patterns',
                'description' => 'Understanding props, events, and advanced component communication techniques.',
                'duration' => 30 * 60,
                'nr_of_files' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 4,
                'title' => 'Slots and Dynamic Components',
                'description' => 'Working with named slots, scoped slots, and dynamic component rendering.',
                'duration' => 25 * 60,
                'nr_of_files' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 4,
                'title' => 'Component Composition API',
                'description' => 'Understanding and implementing component composition patterns.',
                'duration' => 25 * 60,
                'nr_of_files' => 7,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Keeping existing Full Stack sections (module_id: 7)
            [
                'module_id' => 7,
                'title' => 'JWT Authentication Setup',
                'description' => 'Setting up JWT authentication in Laravel and Vue.js applications.',
                'duration' => 210 * 60,
                'nr_of_files' => 18,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 7,
                'title' => 'Frontend Authentication Flow',
                'description' => 'Implementing authentication flow in Vue.js with Vuex state management.',
                'duration' => 213 * 60,
                'nr_of_files' => 22,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'module_id' => 7,
                'title' => 'Protected Routes and Middleware',
                'description' => 'Implementing route protection and authentication middleware.',
                'duration' => 210 * 60,
                'nr_of_files' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('course_sections')->insert($sections);
    }
}
