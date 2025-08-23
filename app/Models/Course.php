<?php

namespace App\Models;

use App\Models\User;
use App\Models\CourseModule;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        "title", "description", "intro_video_url", "intro_image_url", "status", "nr_of_files", "duration", "created_by", "updated_by"
    ];

    public function createdBy() {
        return $this->belongsTo(User::class, "created_by");
    }

    public function updatedBy() {
        return $this->belongsTo(User::class, "updated_by");
    }

    public function modules() {
        return $this->hasMany(CourseModule::class);
    }

    public function progress() {
        return $this->hasMany(UserCourseProgress::class, "course_id");
    }
}
