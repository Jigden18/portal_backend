<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\JobPreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Org\JobVacancyController as OrgVacancy;
use App\Http\Controllers\Seeker\JobSearchController as SeekerJobs;

// API routes for the application
Route::get('/routes', function () {
    $routes = collect(Route::getRoutes())->map(function ($route) {
        return [
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'method' => implode('|', $route->methods()),
        ];
    });

    return response()->json($routes);
});

Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working without database!'
    ]);
});

// route for user signup and login
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// route for user logout
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// social authentication routes
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);

// route for job categories and user preferences
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/job-categories', [JobPreferenceController::class, 'index']);
    Route::post('/job-preferences', [JobPreferenceController::class, 'store']);
});

// Routes that require authentication (job seeker manages own profile)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);     // Get current user's profile
    Route::post('/profile', [ProfileController::class, 'store']);   // Create profile
    Route::put('/profile', [ProfileController::class, 'update']);   // Update profile
});

// Public or admin-accessible route to view another user's profile
Route::get('/profiles/{userId}', [ProfileController::class, 'showByUser']);

// Routes for organization profile management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::post('/organization', [OrganizationController::class, 'store']);
    Route::put('/organization', [OrganizationController::class, 'update']);

    // View organization by user ID
    Route::get('/organization/{userId}', [OrganizationController::class, 'showByUser']);
});

// Organization (auth required)
Route::middleware(['auth:sanctum', 'has.organization'])->prefix('org')->group(function () {
    Route::get('/vacancies', [OrgVacancy::class, 'index']);
    Route::post('/vacancies', [OrgVacancy::class, 'store']);
    Route::get('/vacancies/{id}', [OrgVacancy::class, 'show']);
    Route::put('/vacancies/{id}', [OrgVacancy::class, 'update']);
    Route::patch('/vacancies/{id}/toggle-status', [OrgVacancy::class, 'toggleStatus']);
    Route::delete('/vacancies/{id}', [OrgVacancy::class, 'destroy']);
});

// Seeker (public)
Route::middleware(['auth:sanctum', 'has.profile'])->prefix('jobs')->group(function () {
    Route::get('/filters/options', [SeekerJobs::class, 'filterOptions']); // dynamic filters
    Route::get('/', [SeekerJobs::class, 'index']);         // list with filters
    Route::get('/{id}', [SeekerJobs::class, 'show']);      // job detail
});