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
     * Update limited profile fields (occupation, address, photo)
     */
    public function update(Request $request)
    {
        $profile = Profile::firstWhere('user_id', Auth::id());

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        try {
            // Debug: Log everything about the request
            \Log::info('=== UPDATE REQUEST DEBUG ===');
            \Log::info('HTTP Method:', [$request->method()]);
            \Log::info('Content Type:', [$request->header('Content-Type')]);
            \Log::info('Raw Content:', [$request->getContent()]);
            \Log::info('All Input:', $request->all());
            \Log::info('Request Input Keys:', array_keys($request->all()));
            \Log::info('Has occupation?', [$request->has('occupation')]);
            \Log::info('Has address?', [$request->has('address')]);
            \Log::info('Occupation value:', [$request->input('occupation')]);
            \Log::info('Address value:', [$request->input('address')]);

            // For form-data with PUT requests, we need to be more explicit
            $occupation = $request->input('occupation');
            $address = $request->input('address');

            \Log::info('Extracted values:', [
                'occupation' => $occupation,
                'address' => $address
            ]);

            // Manual validation instead of using validate() method
            $errors = [];
            
            if ($occupation !== null && (!is_string($occupation) || strlen($occupation) > 255)) {
                $errors['occupation'] = ['Occupation must be a string with maximum 255 characters'];
            }
            
            if ($address !== null && (!is_string($address) || strlen($address) > 255)) {
                $errors['address'] = ['Address must be a string with maximum 255 characters'];
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 422);
            }

            $updates = [];

            // Update only fields that are present and not empty
            if ($request->has('occupation') && $occupation !== null && $occupation !== '') {
                $updates['occupation'] = $occupation;
            }
            
            if ($request->has('address') && $address !== null && $address !== '') {
                $updates['address'] = $address;
            }

            // Handle photo removal
            if ($request->has('remove_photo') && $request->input('remove_photo')) {
                if ($profile->photo_public_id) {
                    try {
                        $cld = new \Cloudinary\Cloudinary([
                            'cloud' => [
                                'cloud_name' => config('cloudinary.cloud.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME'),
                                'api_key' => config('cloudinary.cloud.api_key') ?? env('CLOUDINARY_API_KEY'),
                                'api_secret' => config('cloudinary.cloud.api_secret') ?? env('CLOUDINARY_API_SECRET'),
                            ],
                            'url' => ['secure' => true],
                        ]);
                        $cld->uploadApi()->destroy($profile->photo_public_id);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to delete photo from Cloudinary: ' . $e->getMessage());
                    }
                }

                $parts = explode(' ', $profile->full_name);
                $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
                $updates['photo_url'] = "https://ui-avatars.com/api/?name={$initials}&background=random";
                $updates['photo_public_id'] = null;
            }
            // Handle new photo upload
            elseif ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                try {
                    $path = $request->file('photo')->getRealPath();

                    $cld = new \Cloudinary\Cloudinary([
                        'cloud' => [
                            'cloud_name' => config('cloudinary.cloud.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME'),
                            'api_key' => config('cloudinary.cloud.api_key') ?? env('CLOUDINARY_API_KEY'),
                            'api_secret' => config('cloudinary.cloud.api_secret') ?? env('CLOUDINARY_API_SECRET'),
                        ],
                        'url' => ['secure' => true],
                    ]);

                    if ($profile->photo_public_id) {
                        try {
                            $cld->uploadApi()->destroy($profile->photo_public_id);
                        } catch (\Exception $e) {
                            \Log::warning('Failed to delete old photo: ' . $e->getMessage());
                        }
                    }

                    $res = $cld->uploadApi()->upload($path, [
                        'folder' => 'profile_photos',
                        'verify' => false
                    ]);

                    $updates['photo_url'] = $res['secure_url'] ?? $profile->photo_url;
                    $updates['photo_public_id'] = $res['public_id'] ?? $profile->photo_public_id;

                } catch (\Exception $e) {
                    \Log::error('Photo upload failed: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload photo: ' . $e->getMessage()
                    ], 500);
                }
            }

            \Log::info('Final updates array:', $updates);

            if (empty($updates)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected',
                    'data' => $profile
                ], 200);
            }

            // Apply updates
            $updateResult = $profile->update($updates);
            \Log::info('Update result:', ['success' => $updateResult]);

            // Get fresh data
            $updatedProfile = $profile->fresh();
            \Log::info('Profile after update:', $updatedProfile->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $updatedProfile
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Profile update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }
}
