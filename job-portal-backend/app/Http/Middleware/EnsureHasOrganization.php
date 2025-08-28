<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureHasOrganization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->organization) {
            return response()->json([
                'success' => false,
                'message' => 'You must create an organization profile first.'
            ], 403);
        }

        // Attach organization to the request for easy access
        $request->merge(['organization' => $user->organization]);

        return $next($request);
    }
}
