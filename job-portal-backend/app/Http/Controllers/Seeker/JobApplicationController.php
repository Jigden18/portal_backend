<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use Illuminate\Validation\ValidationException;

class JobApplicationController extends Controller
{
    /**
     * Store a new job application
     */
    public function store(Request $request, $jobId)
    {
        $profile = $request->profile; // jobseeker profile via middleware

        // Check if job exists
        $job = JobVacancy::findOrFail($jobId);

        // Prevent duplicate applications
        if (JobApplication::where('job_id', $job->id)
                          ->where('jobseeker_id', $profile->id)
                          ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied for this job.'
            ], 400);
        }

        // Validate PDF
        $validated = $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:2048',
        ]);

        $pdfPath = null;

        if ($request->hasFile('pdf') && $request->file('pdf')->isValid()) {
            $path = $request->file('pdf')->getRealPath();

            $cld = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => config('cloudinary.cloud.api_key') ?? env('CLOUDINARY_API_KEY'),
                    'api_secret' => config('cloudinary.cloud.api_secret') ?? env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true],
            ]);

            $res = $cld->uploadApi()->upload($path, [
                'folder' => env('CLOUDINARY_RESUME_FOLDER', 'resumes'), // PDFs folder
                'resource_type' => 'raw',       // for PDF
                'verify' => false,
                'format' => 'pdf'

            ]);

            $pdfPath = $res['secure_url'] ?? null;
        }

        // Create JobApplication record
        $application = JobApplication::create([
            'job_id' => $job->id,
            'jobseeker_id' => $profile->id,
            'pdf_path' => $pdfPath,
            'status' => 'pending', // default status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully.',
            'data' => $application
        ], 201);
    }
}
