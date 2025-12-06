<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Scoreboard;

class ScoreboardController extends Controller
{
    public function getScoreboard(): JsonResponse
    {
        try {
            $scoreboard = Scoreboard::with(
                // ['user:id,first_name,last_name,email,image']
                [
                    'user' => function ($query) {
                        $query->select('id', 'first_name', 'last_name', 'email', 'image');
                    }
                ]
            )
                ->join('users', 'scoreboards.user_id', '=', 'users.id')
                ->orderBy('scoreboards.score', 'DESC')
                ->orderBy('users.first_name', 'ASC')
                ->select('scoreboards.*')
                ->get();

            return response()->json([
                'success' => true,
                "message" => "Scoreboard retrieved successfully",
                "data" => $scoreboard
            ], 200);



        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                "message" => "Failed to fetch scoreboard data",
                "error" => $th->getMessage()
            ], 500);
        }
    }
}
