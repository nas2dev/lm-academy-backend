<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Course;
use App\Models\UserInfo;
use App\Models\UserList;
use App\Models\Scoreboard;
use App\Models\CourseMaterial;
use Laravel\Sanctum\HasApiTokens;
use App\Models\UserCourseProgress;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use App\Models\UserCourseSectionProgress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'image',
        'academic_year',
        'acc_status',
        'profile_completed',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function lists() {
        return $this->belongsToMany(UserList::class,"user_list_items", "user_id", "list_id")->withTimestamps();
    }

    public function UserInfo() {
        return $this->hasOne(UserInfo::class, "user_id");
    }

    public function scoreboard() {
        return $this->hasOne(Scoreboard::class);
    }

    public function createdCourses() {
        return $this->hasMany(Course::class, "created_by");
    }

    public function updatedCourses() {
        return $this->hasMany(Course::class, "updated_by");
    }

    public function createdCourseMaterials() {
        return $this->hasMany(CourseMaterial::class, "created_by");
    }

    public function updatedCourseMaterials() {
        return $this->hasMany(CourseMaterial::class, "updated_by");
    }

    public function courseSectionProgress() {
        return $this->hasMany(UserCourseSectionProgress::class, "user_id");
    }

    public function courseProgress() {
        return $this->hasMany(UserCourseProgress::class, "user_id");
    }
}