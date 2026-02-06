<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

// استخدم web middleware فقط
Broadcast::routes(['middleware' => ['auth:api']]);

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
})->name('login');
// Broadcasting auth route - exclude from CSRF since we use JWT
Route::post('/broadcasting/auth', function () {
    try {
        \Log::info('Broadcasting auth request', [
            'channel' => request()->input('channel_name'),
            'socket_id' => request()->input('socket_id'),
            'user' => auth('api')->user()?->id
        ]);
        
        $response = Broadcast::auth(request());
        
        // Ensure CORS headers are set
        return $response->header('Access-Control-Allow-Origin', request()->header('Origin') ?? '*')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With');
    } catch (\Exception $e) {
        \Log::error('Broadcasting auth error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'channel' => request()->input('channel_name'),
            'user' => auth('api')->user()?->id
        ]);
        
        return response()->json([
            'error' => 'Authentication failed',
            'message' => $e->getMessage()
        ], 500)->header('Access-Control-Allow-Origin', request()->header('Origin') ?? '*')
            ->header('Access-Control-Allow-Credentials', 'true');
    }
})->middleware(['auth:api'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

// Handle OPTIONS preflight request for broadcasting/auth
Route::options('/broadcasting/auth', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', request()->header('Origin') ?? '*')
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With');
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

// Broadcast channel definitions
// Laravel automatically handles 'private-' prefix from Echo.private()
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    \Log::info('App.Models.User channel auth', [
        'user_exists' => !is_null($user),
        'user_id' => $user->id ?? 'null',
        'requested_id' => $id
    ]);
    
    if (!$user) {
        return false;
    }
    
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    \Log::info('User channel auth', [
        'user_id' => $user->id ?? 'null',
        'requested_id' => $id
    ]);
    
    if (!$user) {
        return false;
    }
    
    return (int) $user->id === (int) $id;
});

Broadcast::channel('leads', function ($user) {
    return $user !== null;
});

Broadcast::channel('listing.{id}', function ($user, $id) {
    \Log::info('Listing channel auth', [
        'user_id' => $user->id ?? 'null',
        'listing_id' => $id
    ]);
    
    // Allow authenticated users to listen to listing channels
    return $user !== null;
});

Broadcast::channel('lead.{id}', function ($user, $id) {
    return true; // أو يمكنك إضافة منطق للصلاحيات
});
Broadcast::channel('lead.updated', function ($user) {
    return $user !== null; // أي مستخدم مصادق يمكنه الاستماع
});
Route::post('/test-broadcast-auth', function (\Illuminate\Http\Request $request) {
    \Log::info('Test Broadcast Auth Called', [
        'channel_name' => $request->input('channel_name'),
        'socket_id' => $request->input('socket_id'),
        'auth_header' => $request->header('Authorization'),
        'all_headers' => $request->headers->all(),
    ]);
    
    // Try to authenticate with JWT
    try {
        $user = auth()->user();
        
        if (!$user) {
            \Log::error('No authenticated user found');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        \Log::info('User authenticated successfully', [
            'user_id' => $user->id,
            'user_name' => $user->name
        ]);
        
        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'channel' => $request->input('channel_name')
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Auth error: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
})->middleware('auth:api');

Route::get('/test-event', function () {
    try {
        $user = \App\Models\User::find(1);
        
        \Log::info('=== TESTING EVENT DIRECTLY ===');
        
        // استخدم الـ event مباشرة بدل الـ notification
        event(new \App\Events\NotificationCreated([
            'id' => 'test-' . time(),
            'type' => 'new_sales_agent',
            'data' => [
                'message' => 'Test event from Laravel2 - ' . now()->toTimeString(),
                'sales_agent_name' => 'Test Agent',
                'notification_type' => 'new_sales_agent'
            ],
            'user_id' => $user->id,
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ]));
        
        \Log::info('✅ Event fired directly');
        
        return response()->json([
            'success' => true,
            'message' => 'Event fired directly - check Pusher and console'
        ]);
        
    } catch (\Exception $e) {
        \Log::error('EVENT ERROR: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
Route::get('/test-request-cancelled', function () {
    $user = \App\Models\User::find(1);
    
    $user->notify(new \App\Notifications\RequestCancelledNotification([
        'request_id' => 123,
        'property_id' => 456,
        'property_title' => 'Test Property',
        'request_type' => 'tour',
        'request_type_text' => 'Property Tour', 
        'cancelled_by_name' => 'Test User',
        'cancelled_by_id' => 1,
        'cancelled_at' => now(),
    ]));
    
    return 'Test RequestCancelledNotification sent!';
});
Route::get('{any}', function () {
    return view('welcome'); 
})->where('any', '^(?!api).*$');
