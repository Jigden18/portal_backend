<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\JobPreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Org\JobVacancyController as OrgVacancy;
use App\Http\Controllers\Seeker\JobSearchController as SeekerJobs;
use App\Http\Controllers\Seeker\JobBookmarkController; 
use App\Http\Controllers\Seeker\JobApplicationController;
use App\Http\Controllers\Org\OrganizationApplicationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\VideoCallController;
use App\Http\Controllers\ChatSearchController;

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

/** Job Vacancies APIs */
// Organization CRUD routes for managing job vacancies
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

// Job Bookmarks (Seeker)
Route::middleware(['auth:sanctum', 'has.profile'])->group(function () {
    Route::prefix('jobs')->group(function () {
        // Route::get('/jobs/{id}', [SeekerJobs::class, 'show'])->whereNumber('id');
        Route::post('/{id}/save', [JobBookmarkController::class, 'store'])->whereNumber('id'); // POST /api/jobs/{id}/save
        Route::delete('/{id}/unsave', [JobBookmarkController::class, 'destroy'])->whereNumber('id'); // DELETE /api/jobs/{id}/unsave});
        Route::get('/saved', [JobBookmarkController::class, 'index']); // GET /api/jobs/saved
    });
});

// Job Applications (Seeker)
Route::middleware(['auth:sanctum', 'has.profile']) // middleware sets $request->profile
    ->post('/jobs/{job}/applications', [JobApplicationController::class, 'store']);

// Organization routes to check applications (auth + organization middleware)
Route::middleware(['auth:sanctum', 'has.organization'])->group(function () {
    Route::get('/jobs/{job}/applications', [OrganizationApplicationController::class, 'index']);
    Route::get('/applications/{application}', [OrganizationApplicationController::class, 'show']);
});
// Organization updating applicant status
Route::middleware(['auth:sanctum']) // must be an org user
    ->patch('/applications/{application}/status', [OrganizationApplicationController::class, 'updateStatus']);

// Notification routes (Seeker)
Route::middleware(['auth:sanctum', 'has.profile'])->group(function () {
    // View notifications
    Route::get('/seeker/notifications', function (Request $request) {
        $profile = $request->user()->profile;

        return response()->json([
            'notifications' => $profile->notifications,
            'unread'        => $profile->unreadNotifications,
        ]);
    });

    // Mark notification as read
    Route::post('/seeker/notifications/{id}/read', function ($id, Request $request) {
        $profile = $request->user()->profile;

        $notification = $profile->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    });
});

// Chat routes (both Seeker and Organization)
// Enhanced Chat routes with complete filtering functionality
Route::middleware('auth:sanctum')->group(function () {
    // Get all conversations for user with optional filters (NEW)
    // Usage: GET /chat/conversations?filter=all|active|archived|unread
    Route::get('/chat/conversations', [ChatController::class, 'getConversations']);
    
    // Start a conversation
    Route::post('/chat/start/{recipient}', [ChatController::class, 'startConversation']);

    // Get messages in a conversation (auto-marks as read)
    Route::get('/chat/messages/{conversation}', [ChatController::class, 'getMessages']);

    // Send a message (supports payload for attachments)
    Route::post('/chat/messages', [ChatController::class, 'sendMessage']);
    
    // Get total unread count (NEW)
    Route::get('/chat/unread-count', [ChatController::class, 'getUnreadCount']);

    // Archive / Unarchive
    Route::patch('/chat/conversations/{conversation}/archive', [ChatController::class, 'archiveConversation']);
    Route::patch('/chat/conversations/{conversation}/unarchive', [ChatController::class, 'unarchiveConversation']);


    // Mark conversation as unread (WhatsApp-style toggle)
    Route::patch('/chat/conversations/{conversation}/mark-unread', [ChatController::class, 'markConversationAsUnread']);

    // Delete conversation (soft delete for user)
    Route::delete('/chat/conversations/{conversation}', [ChatController::class, 'deleteConversation']);

    // Delete message for current user only
    Route::delete('/chat/messages/{message}/for-me', [ChatController::class, 'deleteMessageForMe']);

    // Delete message for everyone (only if unread and sender)
    Route::delete('/chat/messages/{message}/for-everyone', [ChatController::class, 'deleteMessageForEveryone']);
});

// verify configuration for Pusher (or other broadcasting service)
Route::get('/verify-pusher-config', function () {
    return response()->json([
        'broadcast_driver' => config('broadcasting.default'),
        'broadcast_connection' => env('BROADCAST_CONNECTION'),
        'pusher_app_id' => config('broadcasting.connections.pusher.app_id'),
        'pusher_key' => config('broadcasting.connections.pusher.key'),
        'pusher_cluster' => config('broadcasting.connections.pusher.options.cluster'),
        'queue_connection' => config('queue.default'),
    ]);
});


/**
 * Video Call Routes
 */
// Scheduled Interview Calls
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/applications/{applicationId}/interview', [VideoCallController::class, 'getInterview']);
});

// On-demand (user-to-user) video calls
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/conversations/{conversationId}/start-call', [VideoCallController::class, 'startCall']);
    Route::post('/conversations/{conversationId}/end-call', [VideoCallController::class, 'endCall']);
});

// Search for profiles and Organizations
Route::middleware('auth:sanctum')->get('/chat/search', [ChatSearchController::class, 'search']);