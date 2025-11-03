<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Http\Exceptions\HttpResponseException;

class RefreshToken extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->checkForToken($request);

            if($request->user = JWTAuth::parseToken()->authenticate()) {
                if(!$request->user->acc_status) {
                    // Log out the user
                    Auth::logout();

                    // Return a response indicating that the account is disable
                    return response()->json(['error' => 'Account disabled. Please contact the administrators.'], 403);
                }
                return $next($request);
            }

            throw new AuthenticationException('Unauthorized', []);
        } catch (TokenExpiredException $e) {
            throw new HttpResponseException(response()->json([
                'message' => 'Token expired',
            ], 401));
        } catch (\Exception $e) {
            throw new AuthenticationException('Unauthorized', []);
        }
    }
}
