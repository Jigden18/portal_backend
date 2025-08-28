<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureHasProfile
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->profile) {
            return response()->json([
                'success' => false,
                'message' => 'You must create a profile to access this function.'
            ], 403);
        }

        // Attach profile to the request for easy access
        $request->merge(['profile' => $user->profile]);

        return $next($request);
    }
}
