<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Show the authenticated user's profile
     */
    public function show()
    {
        // Fetch profile for the logged-in user
        $profile = Profile::firstWhere('user_id', Auth::id());

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => $profile
        ]);
    }

    /**
     * Show profile of a specific user by their user ID
     * Useful for employers viewing job seekers
     */
    public function showByUser($userId)
    {
        $profile = Profile::firstWhere('user_id', $userId);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => $profile
        ]);
    }

    /**
     * Create a profile (first time for a user)
     */
    public function store(Request $request)
    {
        // Prevent duplicate profiles
        if (Profile::where('user_id', Auth::id())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Profile already exists.'
            ], 400);
        }

        try {
            // Validate the request data
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'date_of_birth' => 'required|date',
                'address' => 'required|string|max:255',
                'occupation' => 'required|string|max:255',
                // We use nullable here because we check for the file's existence below
                'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            // Attach authenticated user details
            $validated['user_id'] = Auth::id();
            $validated['email'] = Auth::user()->email;

            // Initialize photo_url and photo_public_id for the new profile
            $validated['photo_url'] = null;
            $validated['photo_public_id'] = null;

            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $path = $request->file('photo')->getRealPath();

                $cld = new \Cloudinary\Cloudinary([
                    'cloud' => [
                        'cloud_name' => config('cloudinary.cloud.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => config('cloudinary.cloud.api_key')    ?? env('CLOUDINARY_API_KEY'),
                        'api_secret' => config('cloudinary.cloud.api_secret') ?? env('CLOUDINARY_API_SECRET'),
                    ],
                    'url' => ['secure' => true],
                ]);

                $res = $cld->uploadApi()->upload($path, [
                    'folder' => 'profile_photos',
                    'verify' => false
                ]);

                $validated['photo_url']      = $res['secure_url'] ?? null;
                $validated['photo_public_id'] = $res['public_id'] ?? null;
            } else {
                // Generate avatar from initials if no photo uploaded
                $parts = explode(' ', $validated['full_name']);
                $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
                $validated['photo_url'] = "https://ui-avatars.com/api/?name={$initials}&background=random";
            }

            // Create the profile
            $profile = Profile::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully',
                'data' => $profile
            ], 201);

        } catch (ValidationException $e) {
            // Catch Laravel's validation exception
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // Catch Cloudinary or other general errors
            return response()->json([
                'success' => false,
                'message' => 'Failed to create profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing profile (only editable fields)
     */
    public function update(Request $request)
    {
        // Get logged-in user's profile
        $profile = Profile::firstWhere('user_id', Auth::id());

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        try {
            // Validation rules for update (fields optional)
            $validated = $request->validate([
                'address' => 'nullable|string|max:255',
                'occupation' => 'nullable|string|max:255',
                'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            // If a new photo is uploaded
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                // Delete old Cloudinary image if it exists
                if ($profile->photo_public_id) {
                    Cloudinary::destroy($profile->photo_public_id);
                }


                $path = $request->file('photo')->getRealPath();

                $cld = new \Cloudinary\Cloudinary([
                    'cloud' => [
                        'cloud_name' => config('cloudinary.cloud.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => config('cloudinary.cloud.api_key')    ?? env('CLOUDINARY_API_KEY'),
                        'api_secret' => config('cloudinary.cloud.api_secret') ?? env('CLOUDINARY_API_SECRET'),
                    ],
                    'url' => ['secure' => true],
                ]);

                $res = $cld->uploadApi()->upload($path, [
                    'folder' => 'profile_photos',
                    'verify' => false
                ]);

                $profile->photo_url      = $res['secure_url'] ?? null;
                $profile->photo_public_id = $res['public_id'] ?? null;
            }


            // Apply simple field updates
            $profile->fill($validated);

            // Save changes
            $profile->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $profile
            ]);

        } catch (ValidationException $e) {
            // Catch Laravel's validation exception
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // Catch Cloudinary or DB errors
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }
}
