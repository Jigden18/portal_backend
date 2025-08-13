<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\JobPreferenceController;

Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working without database!'
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/job-categories', [JobPreferenceController::class, 'index']);
    Route::post('/job-preferences', [JobPreferenceController::class, 'store']);
});
