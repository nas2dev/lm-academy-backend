<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserList extends Model
{
    protected $fillable = ["list_name"];

    public function users() {
        return $this->belongsToMany(User::class,"user_list_items", "list_id", "user_id");
    }
}
