<?php

namespace App\Http\Controllers;

use Str;
use App\Models\User;
use App\Models\UserInfo;
use App\Mail\SendInviteMail;
use Illuminate\Http\Request;
use App\Mail\PasswordResetMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailVerificationToken;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    private const TOKEN_VERIFICATION_DURATION_MINUTES = 60 * 24 * 7; // 7 days
    private const PASSWORD_RESET_EXPIRATION_MINUTES = 60; // 1 Hour
    protected $user;

    public function __construct()
    {
        $this->user = auth()->user();
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Your email or password is invalid'], 401);
        }

        if(!auth()->user()->acc_status) {
            auth()->logout();
            return response()->json([
                'error' => 'Account disabled. Please contact the administrators.'
            ], 403);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile(): JsonResponse
    {
        $user_id = auth()->id();

         $user = User::where("id", $user_id)->with(["roles", "UserInfo"])->first();

         return response()->json([
            "user" => $user
         ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            $token = JWTAuth::getToken();

            JWTAuth::invalidate($token);

            auth()->logout();

            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $oldToken = JWTAuth::getToken();
            $newToken = auth()->refresh();

            if($oldToken) {
                try {
                    JWTAuth::invalidate($oldToken);
                } catch (\Exception $e) {
                    Log::warning('Failed to invalidate old token: ' . $e->getMessage());
                }
            }

            return $this->respondWithToken($newToken);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $user = User::whereId(auth()->id())->with("roles")->first();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }

    public function sendRegistrationInvite(Request $request) {

        if($this->user->hasRole("Admin")) {
            try {
                $validator = Validator::make($request->all(), [
                    "invited_users" => 'required|string'
                ]);

                if($validator->fails()) {
                    return response()->json($validator->errors()->toJson(), 400);
                }

                $invited_users = array_map('trim', explode(",", $request->invited_users));
                $invited_users = array_unique($invited_users);
                $invited_users = array_values($invited_users);

                $invalid_emails = [];
                $valid_emails = [];
                $existing_users = [];
                $successfully_invited = [];

                foreach($invited_users as $email) {
                    if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $valid_emails[] = $email;
                    }
                    else {
                        $invalid_emails[] = $email;
                    }
                }

                if(!empty($valid_emails)) {
                    $existingUsersQuery = User::whereIn("email", $valid_emails)->pluck("email")->toArray();
                    $existing_users = $existingUsersQuery;
                    $valid_emails = array_diff($valid_emails, $existing_users);
                }

                if(count($valid_emails) > 0) {
                    foreach($valid_emails as $email) {
                        try {
                            $registrionCode = strtoupper(Str::random(6));
                            $token = Str::random(64);

                            EmailVerificationToken::create([
                                "email" => $email,
                                "code" => $registrionCode,
                                "token" => $token
                            ]);

                            $mailData = [
                                "email" => $email,
                                "registrationCode" => $registrionCode,
                                "registrationUrl" => config("app.frontend_url") . "/registration?token=" . $token . "&email=" . urlencode($email),
                                "invitedBy" => $this->user->first_name . " " . $this->user->last_name,
                                "invitationDate" => now()->format('F j, Y \a\t g:i A'),
                                "expirationDate" => now()->addMinutes(self::TOKEN_VERIFICATION_DURATION_MINUTES)->format('F j, Y \a\t g:i A')
                            ];

                            //We need to send the mail

                            Mail::to($email)->send(new SendInviteMail($mailData));


                            $successfully_invited[] = $email;


                        } catch (\Exception $e) {
                            \Log::error("Error sending registration invite: " . [
                                "email" => $email,
                                "error" => $e->getMessage()
                            ]);

                            return response()->json([
                                "message" => "Error sending registration invite",
                                "email" => $email,
                                "error" => $e->getMessage(),
                                "success" => false
                            ], 500);
                        }


                    }
                }

                $invalid_emails_string = implode(", ", $invalid_emails);
                $existing_users_string = implode(", ", $existing_users);
                $successfully_invited_string = implode(", ", $successfully_invited);

                return response()->json([
                    "message" => "Registration invite process completed",
                    "total_invited" => count($invited_users),
                    "successfully_invited" => $successfully_invited_string,
                    "success_count" => count($successfully_invited),
                    "invalid_emails" => $invalid_emails_string,
                    "invalid_count" => count($invalid_emails),
                    "existing_users" => $existing_users_string,
                    "existing_count" => count($existing_users),
                    "success" => true
                ], 200);




            } catch (\Exception $e) {
                \Log::error("Error sending registration invite: " . [
                    "email" => $email,
                    "error" => $e->getMessage()
                ]);

                return response()->json([
                    "message" => "Error sending registration invite",
                    "email" => $email,
                    "error" => $e->getMessage(),
                    "success" => false
                ], 500);
            }


        }
        else {
            return response()->json([
                "message" => "You don't have permission to send registration invite ",
                 "first_name" => $this->user->first_name. " " . $this->user->last_name
            ], 403);
        }

    }

    public function verifyRegistrationToken(Request $request) {

        try {
            $validator = Validator::make($request->all(), [
                "token" => "required|string",
                "email" => "required|email"
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Invalid request parameters",
                    "errors" => $validator->errors()
                ], 400);
            }

            $email = $request->email;
            $token = $request->token;

            $verificationToken = EmailVerificationToken::where("email", $email)
                        ->where("created_at", ">=", now()->subMinutes(self::TOKEN_VERIFICATION_DURATION_MINUTES))
                        ->where("token", $token)->first();


            if(!$verificationToken) {
                return response()->json([
                    "success" => false,
                    "message" => "Invalid or expired registration token"
                ], 404);
            }

            // existing user

            $existingUser = User::where("email", $email)->first();

            if($existingUser) {
                return response()->json([
                    "success" => false,
                    "message" => "User already exists"
                ], 409); // 409 - Conflict
            }

            return response()->json([
                "success" => true,
                "message" => "Registration token verified successfully",
                "token" => $request->token,
                "email" => $request->email,
                "verificationToken" => $verificationToken
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error verifying registration token: " . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "Error verifying registration token",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse {
        try {
           $validator = Validator::make($request->all(), [
            "email" => "required|email|exists:users,email"
           ]);

           if($validator->fails()) {
                return response()->json([
                    'success' => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
           }

           $email = $request->email;
           $user = User::where("email", $email)->first();

           if(!$user) {
            return response()->json([
                'success' => false,
                "message" => "Email not found in our system",
            ], 404);
           }

           $resetToken = Str::random(64);

           //store reset token in database=
           DB::table("password_reset_tokens")->updateOrInsert(
            ["email" => $email],
            [
                "token" => $resetToken,
                "created_at" => now()
            ]
           );
           // prepare mail data
           $resetUrl = config("app.frontend_url") . "/reset-password?token=" . $resetToken . "&email=" . urlencode($email);
           $expirationTime = now()->addMinutes(self::PASSWORD_RESET_EXPIRATION_MINUTES)->format('F j, Y \a\t g:i A');

           $mailData = [
            "reset_url" => $resetUrl,
            "user_name" => $user->first_name . " " . $user->last_name,
            "expiration_time" => $expirationTime
           ];

           Mail::to($email)->send(new PasswordResetMail($mailData));

           \Log::info("Password reset email sent", [
                "email" => $email,
                "user_id" => $user->id
           ]);

           return response()->json([
                'success' => true,
                "message" => "Password reset link sent to your email address",
                "expires_in" => self::PASSWORD_RESET_EXPIRATION_MINUTES
           ], 200);


        } catch (\Exception $e) {
            \Log::error("Error sending password reset email", [
                "email" => $email,
                "error" => $e->getMessage()
           ]);

           return response()->json([
                "success" => false,
                "message" => "Failed to send password reset email. Please try again.",
                "error" => $e->getMessage()
           ], 500);
        }
    }


    public function verifyPasswordResetToken(Request $request): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                "token" => "required|string",
                "email" => "required|email"
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Invalid request parameters",
                    "error" => $validator->errors()
                ], 422);
            }
            $email = $request->email;
            $token = $request->token;

            $resetToken = $this->checkPasswordResetToken($email, $token);

            if(!$resetToken) {
                return response()->json([
                    "success" => false,
                    "message" => "Invalid or expired token"
                ], 400);
            }

            return response()->json([
                "success" => true,
                "message" => "Reset token is valid",
                "email" => $email,
            ]);
        }
        catch (\Exception $e) {
            \Log::error("Error verifying password reset token", [
                "email" => $email,
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to verify reset token",
                "error" => $e->getMessage()
            ], 500);

        }
    }

    public function resetPassword(Request $request): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                "token" => "required|string",
                "email" => "required|email",
                "password" => "required|string|min:8|confirmed"
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validator failed",
                    "error" => $validator->errors()
                ], 422);
            }

            $email = $request->email;
            $token = $request->token;
            $password = $request->password;


            $resetToken = $this->checkPasswordResetToken($email, $token);
            if(!$resetToken) {
                return response()->json([
                    "success" => false,
                    "message" => "Invalid or expired token"
                ], 400);
            }

            // user -> update

            $user = User::where("email", $email)->first();
            if(!$user) {
                return response()->json([
                    "success" => false,
                    "message" => "User not found"
                ], 404);
            }

            $user->password = Hash::make($password);
            // $user->password = bcrypt($password);
            $user->save();

            DB::table("password_reset_tokens")
                ->where("email", $email)
                ->delete();

            return response()->json([
                "success" => true,
                "message" => "Password has been reset successfully"
            ]);

        }
        catch( \Exception $e) {
            \Log::error("Error resetting password", [
                "email" => $email,
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to reset password. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    private function checkPasswordResetToken(string $email, string $token): bool {
        $resetToken = DB::table("password_reset_tokens")
                ->where("email", $email)
                ->where("token", $token)
                ->where("created_at", ">=", now()->subMinutes(self::PASSWORD_RESET_EXPIRATION_MINUTES))
                ->first();

        return $resetToken ? true : false;
    }

    public function register(Request $request): JsonResponse {
        try {
           $validator = Validator::make($request->all(), [
            "registration_code" => "required|string",
            "first_name" => "required|string|max:255",
            "last_name" => "required|string|max:255",
            "gender" => "required|in:male,female,diverse",
            "email" => "required|email|unique:users,email",
            "password" => "required|string|min:8|confirmed",
           ]);

           if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
           }

        //verify registration code
           $verificationToken = EmailVerificationToken::where('code', $request->registration_code)
                                                        ->where("email", $request->email)
                                                        // ->where("token", $request->token)
                                                        ->where("created_at", ">=", now()->subMinutes(self::TOKEN_VERIFICATION_DURATION_MINUTES))
                                                        ->first();

            if(!$verificationToken) {
                return response()->json([
                    "success" => false,
                    "message" => "Invalid or expired registration code"
                ], 400);
            }

            // create user
            $user = User::create([
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
                "gender" => $request->gender,
                "email" => $request->email,
                "password" => Hash::make($request->password),
                "profile_completed" => false,
                "email_verified_at" => now(),
                ]);

            $user->assignRole("User");


            $verificationToken->delete();

            $token = auth()->login($user);

            return response()->json([
                "success" => true,
                "message" => "Registration completed successful. Please complete your profile.",
                "access_token" => $token,
                "token_type" => "bearer",
                "expires_in" => auth()->factory()->getTTL() * 60,
                "user" => $user->load("roles")
            ], 201);


        } catch (\Exception $e) {
            \Log::error("Error registering user", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Registration failed. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function completeProfile(Request $request) {
        try {
            $user = auth()->user(); // academic_year == null, profile_completed == false, image == null etc

            if(!$user) {
                return response()->json([
                    "success" => false,
                    "message" => "Unauthorized"
                ], 401);
            }

            if($user->profile_completed) {
                return response()->json([
                    "success" => false,
                    "message" => "Profile is already completed"
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "academic_year" => 'required|integer|min:' . (date("Y") - 10) . '|max:' . (date("Y")),
                "address" => 'required|string|max:255',
                "telephone" => 'nullable|string|max:20',
                "date_of_birth" => 'required|date|before:today',
                "about" => "nullable|string|max:1000",
                "profile_image" => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120' // 5MB
            ]);

            if($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $imagePath = null;

            if($request->hasFile("profile_image")) {
                $image = $request->file("profile_image");
                $imageName = time() . '_'. $user->id . "." . $image->getClientOriginalExtension(); //12312312321_41.jpg
                $imagePath = $image->storeAs("profile_images", $imageName, 'public'); //storage/profile_images/12312312321_41.jpg
            }

            $user->update([
                "academic_year" => $request->academic_year,
                "date_of_birth" => $request->date_of_birth,
                "image" => $imagePath,
                "profile_completed" => true
            ]);

            // new insert or update on user_infos table
            UserInfo::updateOrCreate(
                ["user_id" => $user->id],
                [
                    "address" => $request->address,
                    "tel" => $request->telephone,
                    "about" => $request->about
                ]
            );

            $user->refresh(); // academic_year == has value, profile_completed == true, image == has value etc
            $user->load('roles', 'UserInfo');

            return response()->json([
                "success" => true,
                "message" => "Profile completed successfully",
                "user" => $user
            ]);
        } catch (\Exception $e) {

            return response()->json([
                "success" => false,
                "message" => "Failed to complete profile. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
