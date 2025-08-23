<?php

namespace App\Models;

use App\Models\CourseModule;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserCourseSectionProgress;

class CourseSection extends Model
{
    protected $fillable = [
        "module_id", "title", "description", "nr_of_files", "duration"
    ];

    public function module() {
        return $this->belongsTo(CourseModule::class, "module_id");
    }

    public function materials() {
        return $this->hasMany(CourseMaterial::class, "course_section_id");
    }

    public function progress() {
        return $this->hasMany(UserCourseSectionProgress::class, "course_section_id");
    }

    // public function users() {
    //     return $this->belongsToMany(User::class, "user_course_section_progress", "course_section_id", "user_id");
    // }
}
