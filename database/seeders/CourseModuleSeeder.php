<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseModuleSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $modules = [
            // Laravel Intro Course (Course ID: 1) - Total: 600 minutes
            [
                'course_id' => 1,
                'title' => 'Introduction to Laravel',
                'description' => 'This introductory module offers a foundational understanding of Laravel framework, covering basic concepts, installation, and development environment setup.',
                'duration' => 200 * 60, // 200 minutes to seconds
                'nr_of_files' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_id' => 1,
                'title' => 'Routes and Views',
                'description' => 'This module delves into the powerful routing and view system of Laravel, teaching how to handle HTTP requests and create dynamic web pages.',
                'duration' => 200 * 60, // 200 minutes to seconds
                'nr_of_files' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_id' => 1,
                'title' => 'Controllers and MVC Design Pattern',
                'description' => 'This module focuses on leveraging Laravel\'s implementation of the MVC architecture, building efficient controllers, and organizing code.',
                'duration' => 200 * 60, // 200 minutes to seconds
                'nr_of_files' => 42,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Vue.js Advance Course (Course ID: 2) - Total: 200 minutes
            [
                'course_id' => 2,
                'title' => 'Advanced Component Architecture',
                'description' => 'Deep dive into Vue.js component system, covering slots, dynamic components, and advanced component communication patterns.',
                'duration' => 80 * 60, // 80 minutes to seconds
                'nr_of_files' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_id' => 2,
                'title' => 'State Management with Vuex',
                'description' => 'Comprehensive coverage of Vuex state management, including actions, mutations, modules, and plugins.',
                'duration' => 60 * 60, // 60 minutes to seconds
                'nr_of_files' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_id' => 2,
                'title' => 'Vue 3 Composition API',
                'description' => 'Exploring the new Composition API, reactive systems, and refactoring options API components.',
                'duration' => 60 * 60, // 60 minutes to seconds
                'nr_of_files' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Full Stack Course (Course ID: 3) - Total: 1900 minutes
            [
                'course_id' => 3,
                'title' => 'Full Stack Authentication',
                'description' => 'Implementing secure authentication across Laravel backend and Vue.js frontend using JWT and best practices.',
                'duration' => 633 * 60, // 633 minutes to seconds
                'nr_of_files' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_id' => 3,
                'title' => 'RESTful API Development',
                'description' => 'Building robust APIs with Laravel and consuming them effectively with Vue.js, including error handling and data validation.',
                'duration' => 633 * 60, // 633 minutes to seconds
                'nr_of_files' => 25,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_id' => 3,
                'title' => 'Real-time Features with WebSockets',
                'description' => 'Implementing real-time functionality using Laravel WebSockets and Vue.js event handling system.',
                'duration' => 634 * 60, // 634 minutes to seconds (added extra minute to reach 1900 total)
                'nr_of_files' => 18,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('course_modules')->insert($modules);
    }
}
