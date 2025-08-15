<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\JobPreferenceController;
use App\Http\Controllers\ProfileController;

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

// route for user profile management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'store']); // Create profile
    Route::put('/profile', [ProfileController::class, 'update']); // Update allowed fields
});
