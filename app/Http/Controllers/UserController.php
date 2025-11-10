<?php

namespace App\Http\Controllers;

use Log;
use Validator;
use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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

    public function changeUserRole(Request $request): mixed {
        try {
            $validator = Validator::make($request->all(), [
                "user_id" => "required|integer|exists:users,id",
                "role" => "required|string|exists:roles,name",
                ]
            );

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $userId = $request->input("user_id");
            $newRole = $request->input("role");

            $user = User::with("roles")->find($userId);

            $currentRole = $user->roles->first()->name;
            if(strtolower($currentRole) == strtolower($newRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has this role',
                ], 400);
            }

            // Remove all current roles and assign new role
            $user->syncRoles([$newRole]);

            // Refresh user data
            $user->refresh();
            $user->load("roles");

            return response()->json([
                "success" => true,
                "message" => "User role updated successfully",
                "user" => $user,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error changing user role", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error changing user role",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function changeAccountStatus(Request $request): mixed {
        try {
            $validator = Validator::make($request->all(), [
                "user_id" => "required|integer|exists:users,id",
                "status" => "required|integer|in:0,1"
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $userId = $request->input("user_id");
            $newStatus = $request->input("status");

            $user = User::find($userId);

            // Check if user already has this status
            if($user->acc_status == $newStatus) {
                $currentStatusText = $user->acc_status ? "active" : "inactive";
                return response()->json([
                    'success' => false,
                    'message' => 'User is already ' . $currentStatusText,
                ], 400);
            }

            // Update account status
            $user->acc_status = $newStatus;
            $user->save();

            return response()->json([
                "success" => true,
                "message" => "User account status updated successfully",
            ], 200);
        }  catch (\Exception $e) {
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

    public function getUserProfileById($id): mixed {
        try {
            $user = User::with("roles", "UserInfo")->find($id);

            if(!$user) {
                return response()->json([
                    "success" => false,
                    "message" => "User not found",
                ], 404);
            }

            return response()->json([
                "success" => true,
                "message" => "User profile retrieved successfully",
                "user" => $user
            ]);
        }  catch (\Exception $e) {
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

    public function updateProfile(Request $request): mixed {
        try {
            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                "first_name" => "required|string|min:2|max:255",
                "last_name" => "required|string|min:2|max:255",
                "gender" => "required|in:male,female,diverse",
                'academic_year' => 'required|integer|min:' . (date("Y") - 80) . '|max:' . (date("Y")),
                "date_of_birth" => "required|date|before:today",
                "about" => "nullable|string|max:1000",
                'address' => 'nullable|string|max:255',
                'tel' => 'nullable|string|max:20',
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $user->update([
                "first_name" => $request->input("first_name"),
                "last_name" => $request->input("last_name"),
                "gender" => $request->input("gender"),
                "academic_year" => $request->input("academic_year"),
                "date_of_birth" => $request->input("date_of_birth"),
            ]);

            UserInfo::updateOrCreate(
                ["user_id" => $user->id],
                [
                    "address" => $request->input("address"),
                    "tel" => $request->input("tel"),
                    "about" => $request->input("about"),
                ]
            );

            $user->refresh();
            $user->load(["roles", "UserInfo"]);

            return response()->json([
                "success" => true,
                "message" => "Profile updated successfully",
                "user" => $user
            ], 200);
        }   catch (\Exception $e) {
            Log::error("Error updating profile", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error updating profile",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function uploadProfileImage(Request $request): mixed {
        try {
            $validator = Validator::make($request->all(), [
                "profile_image" => "required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120" // 5MB
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $oldImagePath = $user->image;
            $image = $request->file("profile_image");
            $imageName = time() . '_'. $user->id. ".". $image->getClientOriginalExtension();
            $path = $image->storeAs("profile_images", $imageName, 'public');

            $user->update(["image" => $path]);
            $this->removeProfileImage($oldImagePath);

            $user->refresh()->load(["roles", "UserInfo"]);

            return response()->json([
                "success" => true,
                "message" => "Profile image uploaded successfully",
                "user" => $user
            ], 200);
        }   catch (\Exception $e) {
            Log::error("Error uploading profile image", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error uploading profile image",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function deleteProfileImage(Request $request): mixed {
        try {
            $user = auth()->user();
            $imagePath = $user->image;

            if(!$imagePath) {
                return response()->json([
                    "success" => false,
                    "message" => "No profile image to delete",
                ], 400);
            }

            $user->update(["image" => null]);
            $this->removeProfileImage($imagePath);

            $user->refresh()->load(["roles", "UserInfo"]);

            return response()->json([
                "success" => true,
                "message" => "Profile image deleted successfully",
                "user" => $user
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error deleting profile image", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error deleting profile image",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request): mixed {
        try {
            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'old_password' => 'required|string|min:8',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            if(!Hash::check($request->input('old_password'), $user->password)) {
                return response()->json([
                    "success" => false,
                    "message" => "The provided password is incorrect.",
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->input('new_password')),
            ]);

            return response()->json([
                "success" => true,
                "message" => "Password changed successfully",
            ], 200);
        }  catch (\Exception $e) {
            Log::error("Error changing password", [
                "user_id" => $user->id,
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error changing password",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    private function removeProfileImage(?string $path): void {
        if($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
