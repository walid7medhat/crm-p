<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\Listing\ListingController;

Route::get('/leads/export', [LeadController::class, 'export'])->name('leads.export');
Route::get('/image/watermark', [ListingController::class, 'watermark'])
    ->name('image.watermark');

// استخدم web middleware فقط
Broadcast::routes(['middleware' => ['auth:api']]);

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
})->name('login');
Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('auth:api');
// Handle OPTIONS preflight request for broadcasting/auth
Route::options('/broadcasting/auth', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', request()->header('Origin') ?? '*')
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With');
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('leads', function ($user) {
    return $user !== null;
});

Broadcast::channel('listing.{id}', function ($user, $id) {
    // Authenticated CRM users may subscribe (matches leads / lead-assignment channels).
    return $user !== null;
});

Broadcast::channel('lead.{id}', function ($user, $id) {
    return $user !== null;
});

Broadcast::channel('lead.updated', function ($user) {
    return $user !== null;
});

Broadcast::channel('lead-assignment', function ($user) {
    // Kanban + Lead Assignment UI subscribe here; allow any authenticated user (JWT/session).
    return $user !== null;
});

Route::get('privacy-policy', function () {
    return view('privacy-policy');
});

// Preview email designs in the browser (e.g. account-activated)
Route::get('preview-email/account-activated', function () {
    return view('emails.account-activated', [
        'userName' => 'Ahmed',
    ]);
})->name('preview-email.account-activated');

Route::get('/fb/from/{id}/leads', [IntegrationController::class, 'fetchMetaLeads']);

Route::get('{any}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');
