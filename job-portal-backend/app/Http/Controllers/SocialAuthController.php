<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Client;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the OAuth provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\Response
     */
    public function redirect($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Handle the OAuth provider callback.
     *
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback($provider)
    {
        try {
            // Use a custom Guzzle client with SSL verification disabled (for local testing only)
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->setHttpClient(new Client(['verify' => false]))
                ->user();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invalid credentials provided.',
                'message' => $e->getMessage() // shows exact error for debugging
            ], 422);
        }

        // Find existing user by provider + provider_id
        $user = User::where('provider', $provider)
                    ->where('provider_id', $socialUser->getId())
                    ->first();

        // If user does not exist, create a new record
        if (!$user) {
            $user = User::create([
                'email'       => $socialUser->getEmail(),
                'password'    => Hash::make(Str::random(16)), // random password
                'provider'    => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
        }

        // Create Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => ucfirst($provider) . ' login successful',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ]
        ]);
    }
}
