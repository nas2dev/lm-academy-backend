<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseMaterialSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $materials = [
            // Materials for "Setting Up Your Laravel Environment" (Section 1)
            [
                'course_section_id' => 1,
                'title' => 'Introduction Video',
                'type' => 'video',
                'content' => 'Welcome to Laravel installation guide. This video will walk you through the setup process.',
                'material_url' => '/materials/videos/laravel-setup-guide.mp4',
                'sort_order' => 1,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_section_id' => 1,
                'title' => 'Required Software and Tools',
                'type' => 'text',
                'content' => 'Before we begin with Laravel, you\'ll need to install: PHP 8.1 or higher, Composer, Node.js, and a code editor like Visual Studio Code. This lesson will guide you through the installation process for each component.',
                'material_url' => null,
                'sort_order' => 2,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_section_id' => 1,
                'title' => 'Laravel Directory Structure Overview',
                'type' => 'image',
                'content' => 'Visual representation of Laravel\'s directory structure',
                'material_url' => '/materials/images/laravel-directory-structure.png',
                'sort_order' => 3,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Materials for "Understanding Laravel's Architecture" (Section 2)
            [
                'course_section_id' => 2,
                'title' => 'Laravel Architecture Diagram',
                'type' => 'image',
                'content' => 'Comprehensive diagram showing Laravel\'s architectural components and their relationships',
                'material_url' => '/materials/images/laravel-architecture.png',
                'sort_order' => 1,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_section_id' => 2,
                'title' => 'Service Container Deep Dive',
                'type' => 'video',
                'content' => 'Detailed explanation of Laravel\'s Service Container with practical examples',
                'material_url' => '/materials/videos/service-container-explained.mp4',
                'sort_order' => 2,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_section_id' => 2,
                'title' => 'Service Container and Service Providers',
                'type' => 'text',
                'content' => 'Learn about Laravel\'s powerful service container and how service providers bootstrap your application. Understanding dependency injection and container bindings.',
                'material_url' => null,
                'sort_order' => 3,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Materials for "Introduction to Blade Templating" (Section 3)
            [
                'course_section_id' => 3,
                'title' => 'Blade Syntax Overview',
                'type' => 'video',
                'content' => 'Complete walkthrough of Blade template syntax and features',
                'material_url' => '/materials/videos/blade-syntax-guide.mp4',
                'sort_order' => 1,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_section_id' => 3,
                'title' => 'Blade Directives Cheat Sheet',
                'type' => 'image',
                'content' => 'Quick reference guide for all Blade directives',
                'material_url' => '/materials/images/blade-cheatsheet.png',
                'sort_order' => 2,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_section_id' => 3,
                'title' => 'Layout Management with Blade',
                'type' => 'text',
                'content' => 'Learn about template inheritance using @extends, @section, and @yield directives. Creating reusable layouts and components.',
                'material_url' => null,
                'sort_order' => 3,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Additional file example
            [
                'course_section_id' => 3,
                'title' => 'Blade Templates Starter Kit',
                'type' => 'file',
                'content' => 'Download this starter kit to get pre-built Blade templates for common use cases',
                'material_url' => '/materials/files/blade-starter-kit.pdf',
                'sort_order' => 4,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

        ];

        DB::table('course_materials')->insert($materials);
    }
}
