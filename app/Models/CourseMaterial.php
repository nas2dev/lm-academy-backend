<?php

namespace App\Models;

use App\Models\User;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Model;

class CourseMaterial extends Model
{
    protected $fillable = [
        "course_section_id", "title", "type", "content", "material_url", "sort_order", "created_by", "updated_by"
    ];

    public function section() {
        return $this->belongsTo(CourseSection::class, "course_section_id");
    }

    public function creator() {
        return $this->belongsTo(User::class, "created_by");
    }

    public function updator() {
        return $this->belongsTo(User::class, "updated_by");
    }
}
