<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Show profile of authenticated user
    public function show()
    {
        $profile = Profile::where('user_id', Auth::id())->first();
        return response()->json($profile);
    }

    // Create profile (first time)
    public function store(Request $request)
    {
        // Prevent duplicate profile creation
        if (Profile::where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'Profile already exists.'], 400);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'address' => 'required|string|max:255',
            'occupation' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $validated['user_id'] = Auth::id();
        $validated['email'] = Auth::user()->email; // email from authenticated user

        // Photo handling
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile_photos', 'public');
            $validated['photo_url'] = Storage::url($path);
        } else {
            $initials = collect(explode(' ', $validated['full_name']))
                        ->only([0, -1]) // take first and last elements
                        ->map(fn($word) => strtoupper(substr($word, 0, 1)))
                        ->implode('');
            $validated['photo_url'] = "https://ui-avatars.com/api/?name={$initials}&background=random";
        }

        $profile = Profile::create($validated);

        return response()->json([
            'message' => 'Profile created successfully',
            'profile' => $profile
        ]);
    }

    // Update editable fields only
    public function update(Request $request)
    {
        $profile = Profile::where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        // Only update allowed fields
        if ($request->has('date_of_birth')) {
            $profile->date_of_birth = $validated['date_of_birth'];
        }
        if ($request->has('address')) {
            $profile->address = $validated['address'];
        }
        if ($request->has('occupation')) {
            $profile->occupation = $validated['occupation'];
        }

        // Handle photo update
        if ($request->hasFile('photo')) {
            if ($profile->photo_url && str_contains($profile->photo_url, 'storage/')) {
                Storage::delete(str_replace('/storage/', 'public/', $profile->photo_url));
            }
            $path = $request->file('photo')->store('profile_photos', 'public');
            $profile->photo_url = Storage::url($path);
        }

        $profile->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile
        ]);
    }
}
