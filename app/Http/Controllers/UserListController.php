<?php

namespace App\Http\Controllers;

use Validator;
use App\Models\User;
use App\Models\UserList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserListController extends Controller
{
    private const DEFAULT_PER_PAGE = 10;
    private const MIN_PER_PAGE = 3;
    private const MAX_PER_PAGE = 100;

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('perPage', self::DEFAULT_PER_PAGE);
            $page = $request->get('page', 1);
            $lists = UserList::paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'message' => 'Lists retrieved successfully',
                'lists' => $lists
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error retrieving lists", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lists. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'list_name' => 'required|string|between:2,100|unique:user_lists',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'integer|exists:users,id',
                'emails' => 'nullable|array',
                'emails.*' => 'email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userIds = $request->input('user_ids', []);
            $emails = $request->input('emails', []);
            $invalidEmails = [];
            $notFoundEmails = [];

            // Look up user IDs from emails
            $userIdsFromEmails = [];
            if (!empty($emails) && is_array($emails)) {
                foreach ($emails as $email) {
                    // Validate email format (already validated by validator, but double-check)
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $invalidEmails[] = $email;
                        continue;
                    }

                    // Find user by email
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        $userIdsFromEmails[] = $user->id;
                    } else {
                        $notFoundEmails[] = $email;
                    }
                }
            }

            // Combine user IDs from both sources and remove duplicates
            $allUserIds = array_unique(array_merge($userIds, $userIdsFromEmails));

            // Create list and attach users in a transaction
            $list = DB::transaction(function () use ($request, $allUserIds) {
                // Create the list
                $list = UserList::create([
                    'list_name' => $request->list_name
                ]);

                // Attach users if any were provided
                if (!empty($allUserIds)) {
                    $attachData = [];
                    $now = now();
                    foreach ($allUserIds as $userId) {
                        $attachData[$userId] = [
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }
                    $list->users()->attach($attachData);
                }

                return $list;
            });

            // Load the list with users for response
            $list->load('users:id,first_name,last_name,email');

            // Prepare response
            $response = [
                'success' => true,
                'message' => 'List created successfully',
                'list' => $list
            ];

            // Add warnings if there were invalid/not found emails
            $warnings = [];
            if (!empty($invalidEmails)) {
                $warnings[] = 'Invalid email format(s): ' . implode(', ', $invalidEmails);
            }
            if (!empty($notFoundEmails)) {
                $warnings[] = 'User(s) not found with email(s): ' . implode(', ', $notFoundEmails);
            }
            if (!empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            Log::error("Error creating list", [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create list. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function updateListName(Request $request, $id)
    {
        try {
            $list = UserList::find($id);
            if (!$list) {
                return response()->json([
                    'success' => false,
                    'message' => 'List does not exist'
                ], 404);
            }


            $validator = Validator::make($request->all(), [
                'list_name' => 'required|string|between:2,100|unique:user_lists,list_name,' . $id
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $list->update([
                'list_name' => $request->list_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'List updated successfully',
                'list' => $list
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error updating list", [
                'list_id' => $id,
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update list. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $list = UserList::find($id);
            if (!$list) {
                return response()->json([
                    'success' => false,
                    'message' => 'List does not exist'
                ], 404);
            }

            $list->delete();

            return response()->json([
                'success' => true,
                'message' => 'List deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error deleting list", [
                'list_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete list. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getUsersInList(Request $request, $id)
    {
        try {
            $users_list = UserList::with([
                'users' => function ($query) {
                    $query->select('users.id', 'users.first_name', 'last_name', 'email');
                }
            ])
                ->where('id', $id)->first();

            if (!$users_list) {
                return response()->json([
                    'success' => false,
                    'message' => 'List does not exist'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'users_list' => $users_list
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error retrieving users in list", [
                'list_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllAvailableUsers(Request $request, $id)
    {
        try {
            $list = UserList::find($id);
            if (!$list) {
                return response()->json([
                    'success' => false,
                    'message' => 'List does not exist'
                ], 404);
            }

            $existingUserIds = $list->users()->pluck('users.id')->toArray();

            $available_users = User::whereNotIn('id', $existingUserIds)
                ->select('id', 'first_name', 'last_name', 'email')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Available users retrieved successfully',
                'available_users' => $available_users
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error retrieving available users", [
                'list_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available users. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addUsersToList(Request $request, $id)
    {
        try {
            $list = UserList::find($id);
            if (!$list) {
                return response()->json([
                    'success' => false,
                    'message' => 'List does not exist'
                ], 404);
            }

            $userId = $request->user_id;
            $user_id = User::find($userId);
            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not exist'
                ], 404);
            }

            $existingUser = UserList::with([
                'users' => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                }
            ])->where('id', $id)->first();

            if (!$existingUser->users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user is already added to this list!'
                ], 409);
            }

            $list->users()->attach($user_id, ['created_at' => now(), 'updated_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'User added successfully',
                'list' => $list
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error adding user to list", [
                'list_id' => $id,
                'user_id' => $request->user_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add user to list. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeUserFromList(Request $request, $id)
    {
        try {
            $list = UserList::find($id);
            if (!$list) {
                return response()->json([
                    'success' => false,
                    'message' => 'List does not exist'
                ], 404);
            }

            $userId = $request->user_id;
            $user_id = User::find($userId);
            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not exist'
                ], 404);
            }

            $existingUser = UserList::with([
                'users' => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                }
            ])->where('id', $id)->first();

            if ($existingUser->users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not exist on this list!'
                ], 409);
            }

            $list->users()->detach($userId);

            return response()->json([
                'success' => true,
                'message' => 'User removed successfully from list',
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error removing user from list", [
                'list_id' => $id,
                'user_id' => $request->user_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove user from list. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // pick a winner from list
    public function pickRandomWinner(Request $request, $id)
    {
        try {
            $list = UserList::find($id);
            if (!$list) {
                return response()->json([
                    'success' => false,
                    'message' => 'List does not exist'
                ], 404);
            }

            // Get users from the list
            $listUsers = $list->users;

            // Get additional emails from request (optional)
            $additionalEmails = $request->input('additional_emails', []);

            // Validate additional emails if provided
            $validAdditionalUsers = collect();
            $invalidEmails = [];

            if (!empty($additionalEmails) && is_array($additionalEmails)) {
                foreach ($additionalEmails as $email) {
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $invalidEmails[] = $email;
                        continue;
                    }

                    // Check if user exists with this email
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        // Check if user is already in the list (avoid duplicates)
                        $alreadyInList = $listUsers->contains('id', $user->id);
                        if (!$alreadyInList) {
                            $validAdditionalUsers->push($user);
                        }
                    } else {
                        // Create a temporary user object for emails that don't exist in database
                        // This allows picking winners from emails even if they're not registered users
                        $tempUser = (object) [
                            'id' => null,
                            'first_name' => '',
                            'last_name' => '',
                            'email' => $email
                        ];
                        $validAdditionalUsers->push($tempUser);
                    }
                }
            }

            // Combine list users with valid additional users
            // Convert Eloquent Collection to regular Collection to allow merging with stdClass objects
            $allParticipants = collect($listUsers->all())->merge($validAdditionalUsers);

            // Check if we have at least 2 participants
            if ($allParticipants->count() < 2) {
                $message = 'At least 2 participants are required to pick a random winner';
                if ($allParticipants->count() === 0) {
                    $message = 'No users in this list to pick a winner from';
                } else if ($allParticipants->count() === 1) {
                    $message = 'This list cannot generate a random winner because it has only 1 user';
                }

                // Prepare error response
                $errorResponse = [
                    'success' => false,
                    'message' => $message
                ];

                // Add warnings if there were invalid emails (even on error)
                $warnings = [];
                if (!empty($invalidEmails)) {
                    $warnings[] = 'Invalid email format(s): ' . implode(', ', $invalidEmails);
                }
                if (!empty($warnings)) {
                    $errorResponse['warnings'] = $warnings;
                }

                return response()->json($errorResponse, 422);
            }

            // Pick random winner from combined pool
            $selectedWinner = $allParticipants->random();

            // Format winner data (handle both User models and temporary objects)
            $winner = [
                'id' => $selectedWinner->id ?? null,
                'first_name' => $selectedWinner->first_name ?? '',
                'last_name' => $selectedWinner->last_name ?? '',
                'email' => $selectedWinner->email ?? ''
            ];

            // Prepare response
            $response = [
                'success' => true,
                'message' => 'Winner selected successfully',
                'winner_user' => $winner
            ];

            // Add warnings if there were invalid emails (optional - for debugging)
            $warnings = [];
            if (!empty($invalidEmails)) {
                $warnings[] = 'Invalid email format(s): ' . implode(', ', $invalidEmails);
            }
            if (!empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error("Error picking random winner", [
                'list_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to pick random winner. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
