<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::create(2025, 1, 25);

        $courses = [
            [
                'id' => 1,
                'title' => 'Laravel Intro',
                'description' => 'This comprehensive Laravel course is designed to guide both novice and intermediate developers through the fundamentals and advanced features of the Laravel framework. As one of the most popular PHP frameworks, Laravel offers an elegant syntax and is aimed at enhancing both the speed and simplicity of web development. This course will cover everything from setting up a development environment, routing, middleware, and handling data with Eloquent ORM, to more advanced topics such as authentication, authorization, and package development. By the end of this course, students will have built a complete application from scratch, showcasing their ability to harness Laravel\'s features to create highly responsive, scalable, and maintainable web applications.',
                'intro_video_url' => null,
                'intro_image_url' => null,
                'status' => 1,
                'created_by' => 1,
                'updated_by' => null,
                'duration' => 600 * 60,
                'nr_of_files' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'title' => 'Vue.js Advance',
                'description' => 'Dive deep into advanced Vue.js concepts with this comprehensive course designed for developers looking to master modern frontend development. This course explores complex state management with Vuex, advanced component patterns, custom directives, and optimization techniques. You\'ll learn about Vue 3\'s Composition API, reactive systems, and how to build large-scale applications with Vue.js. The curriculum includes hands-on projects focusing on real-time applications, advanced routing scenarios, and integration with backend APIs. By course completion, you\'ll be equipped with the knowledge to build sophisticated, high-performance Vue.js applications and understand the framework\'s inner workings.',
                'intro_video_url' => null,
                'intro_image_url' => null,
                'status' => 1,
                'created_by' => 2,
                'updated_by' => null,
                'duration' => 200 * 60,
                'nr_of_files' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'title' => 'Full stack Laravel and Vue.js',
                'description' => 'Master full-stack web development with this comprehensive course combining Laravel and Vue.js. This intensive program bridges the gap between backend and frontend development, teaching you how to create modern, full-stack applications. You\'ll learn advanced Laravel APIs, Vue.js component architecture, real-time features with WebSockets, and how to implement authentication and authorization across both platforms. The course covers deployment strategies, testing methodologies, and best practices for building scalable applications. Through practical projects, you\'ll gain hands-on experience in creating full-featured applications that integrate seamlessly between Laravel\'s robust backend and Vue.js\'s reactive frontend, preparing you for real-world full-stack development challenges.',
                'intro_video_url' => null,
                'intro_image_url' => null,
                'status' => 0,
                'created_by' => 3,
                'updated_by' => null,
                'duration' => 1900 * 60,
                'nr_of_files' => 42,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('courses')->insert($courses);
    }
}