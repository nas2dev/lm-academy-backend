<?php

namespace App\Models;

use App\Models\User;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Model;

class UserCourseSectionProgress extends Model
{
    protected $fillable = [
        "user_id", "course_section_id"
    ];

    public function user() {
        return $this->belongsTo(User::class, "user_id");
    }

    public function courseSection() {
        return $this->belongsTo(CourseSection::class, "course_section_id");
    }
}
