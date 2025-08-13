<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobCategory;
use Illuminate\Support\Facades\Auth;

class JobPreferenceController extends Controller
{
    // Show all categories
    public function index()
    {
        return response()->json(JobCategory::all());
    }

    // Store selected categories
    public function store(Request $request)
    {
        $validated = $request->validate([
            'categories' => 'required|array|min:3|max:5',
            'categories.*' => 'exists:job_categories,id'
        ]);

        $user = Auth::user();
        $user->jobCategories()->sync($validated['categories']);

        return response()->json([
            'message' => 'Preferences saved successfully!',
            'next_url' => 'profile' // replace with your actual route
        ]);
    }
}
