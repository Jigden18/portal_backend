<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Cloudinary\Cloudinary;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /**
     * Show the authenticated user's organization
     */
    public function show()
    {
        $organization = Organization::firstWhere('user_id', Auth::id());

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Organization retrieved successfully',
            'data' => $organization
        ]);
    }




        /**
     * Show organization profile of a specific user by user ID
     * Useful for job seekers viewing employers
     */
    public function showByUser($userId)
    {
        $organization = Organization::firstWhere('user_id', $userId);

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Organization retrieved successfully',
            'data' => $organization
        ]);
    }


    /**
     * Store a new organization profile
     */
    public function store(Request $request)
    {
        if (Organization::where('user_id', Auth::id())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Organization profile already exists'
            ], 400);
        }

        try {
            $validated = $request->validate([
                'name'             => 'required|string|unique:organizations|max:255',
                'email'            => 'required|email|unique:organizations|max:255',
                'established_date' => 'required|date',
                'country'          => 'required|string|max:100',
                'address'          => 'required|string|max:255',
                'logo'             => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            $validated['user_id'] = Auth::id();

            $validated['logo_url'] = null;
            $validated['logo_public_id'] = null;

        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $path = $request->file('logo')->getRealPath();

            $cld = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true],
            ]);

            $res = $cld->uploadApi()->upload($path, [
                'folder' => config('cloudinary.upload.organization_folder'),
                'verify' => false
            ]);

            $validated['logo_url'] = $res['secure_url'] ?? null;
            $validated['logo_public_id'] = $res['public_id'] ?? null;
        } else {
            // Generate initials avatar if no logo is uploaded
            $parts = explode(' ', $validated['name']);
            $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
            $validated['logo_url'] = "https://ui-avatars.com/api/?name={$initials}&background=random";
        }

            $organization = Organization::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Organization created successfully',
                'data' => $organization
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update organization details
     */
    public function update(Request $request)
    {
        $organization = Organization::firstWhere('user_id', Auth::id());

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => [
                    'sometimes', 'string', 'max:255',
                    Rule::unique('organizations')->ignore($organization->id),
                ],
                'email' => [
                    'sometimes', 'email', 'max:255',
                    Rule::unique('organizations')->ignore($organization->id),
                ],
                'established_date' => 'sometimes|date',
                'country'          => 'sometimes|nullable|string|max:100',
                'address'          => 'sometimes|string|max:255',
                'logo'             => 'sometimes|nullable|image|mimes:jpg,jpeg,png|max:2048',
                'remove_logo'      => 'sometimes|boolean',
            ]);

            $cld = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true],
            ]);

            // Case 1: New logo uploaded
            if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                if ($organization->logo_public_id) {
                    try {
                        $cld->uploadApi()->destroy($organization->logo_public_id);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to delete old logo: ' . $e->getMessage());
                    }
                }

                $path = $request->file('logo')->getRealPath();
                $res = $cld->uploadApi()->upload($path, [
                    'folder' => config('cloudinary.upload.organization_folder'),
                    'verify' => false
                ]);

                $validated['logo_url'] = $res['secure_url'] ?? $organization->logo_url;
                $validated['logo_public_id'] = $res['public_id'] ?? $organization->logo_public_id;

            // Case 2: Logo removed explicitly
            } elseif ($request->boolean('remove_logo')) {
                if ($organization->logo_public_id) {
                    try {
                        $cld->uploadApi()->destroy($organization->logo_public_id);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to delete old logo: ' . $e->getMessage());
                    }
                }

                $parts = explode(' ', $organization->name);
                $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
                $validated['logo_url'] = "https://ui-avatars.com/api/?name={$initials}&background=random";
                $validated['logo_public_id'] = null;
            }

            $organization->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Organization updated successfully',
                'data' => $organization->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update organization: ' . $e->getMessage()
            ], 500);
        }
    }
}
