<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class VerifyToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization') ?? $request->header('authorization') ?? '';
        
        if (empty($authHeader)) {
            return response()->json(['error' => 'No authorization header'], 401);
        }
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return response()->json(['error' => 'Invalid authorization format'], 401);
        }
        
        $token = $matches[1];
        
        try {
            $user = DB::table('users')->where('token', $token)->first();
            
            if (!$user) {
                return response()->json(['error' => 'Invalid token'], 401);
            }
            
            // Attach user to request for use in controllers
            $request->merge(['auth_user' => (array)$user]);
            
            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
