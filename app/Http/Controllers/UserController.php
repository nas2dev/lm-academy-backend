<?php

namespace App\Http\Controllers;

use Log;
use Validator;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //
    private const DEFAULT_PER_PAGE = 10;
    private const MIN_PER_PAGE = 3;
    private const MAX_PER_PAGE = 100;
    public function allUsers(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                "page" => "nullable|integer|min:1",
                "per_page" => "nullable|integer|min:".self::MIN_PER_PAGE."|max:".self::MAX_PER_PAGE,
                "searchTerm" => "nullable|string|max:255"
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $page = $request->input("page", 1);
            $perPage = $request->input("per_page", self::DEFAULT_PER_PAGE);
            $searchTerm = $request->input("searchTerm");

            $query = User::with(["roles", "UserInfo"]);

            // Apply search filter if searchTerm is provided
            if(!empty($searchTerm)) {
                $query->where(function($q) use ($searchTerm) {
                    $q->where("first_name", "like", "%". $searchTerm . '%')
                        ->orWhere("last_name", "like", "%". $searchTerm . '%')
                        ->orWhere("email", "like", "%". $searchTerm . '%')
                        ->orWhereHas("UserInfo", function($q) use ($searchTerm) {
                            $q->where("tel", "like", "%". $searchTerm . '%');
                        });
                });
            }

            $query->orderBy("id", "desc");

            // Paginate the results
            $users = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                "success" => true,
                "message" => "Users fetched successfully",
                "users" => $users
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error getting all users", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error getting all users",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
