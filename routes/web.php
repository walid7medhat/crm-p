<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\Listing\ListingController;

Route::get('/leads/export', [LeadController::class, 'export'])
    ->middleware(['jwt.auth', 'permission:leads-list'])
    ->name('leads.export');
Route::get('/image/watermark', [ListingController::class, 'watermark'])
    ->name('image.watermark');

// استخدم web middleware فقط
Broadcast::routes(['middleware' => ['jwt.auth']]);

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
})->name('login');
Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('jwt.auth');
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

Broadcast::channel('crm-campaigns', function ($user) {
    return $user !== null;
});

// Super Admin Live Watch — presence + timeline (strict role gate).
Broadcast::channel('live-watch', function ($user) {
    return $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
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

Route::get('/fb/from/{id}/leads', [IntegrationController::class, 'fetchMetaLeads'])
    ->middleware(['jwt.auth', 'role:super_admin|admin']);

// The old public photoshoot booking page was retired (requests are made from "Photo Shoots"
// inside the CRM). Answer a real 404 instead of loading the app shell.
Route::get('book-photoshoot', fn () => abort(404));

// Separate Luxvant storage: /storage/luxvant/... files that are not in main
// storage (new uploads) are served from the luxvant_public disk. Public
// allowlisted images only — private files always go through company-files.
Route::get('storage/luxvant/{path}', function (string $path) {
    $path = \App\Support\Brand\CompanyFilePolicy::normalize('luxvant/'.$path);
    if (! str_starts_with($path, 'luxvant/')
        || ! \App\Support\Brand\CompanyFilePolicy::isPublicAllowlisted($path)) {
        abort(404);
    }

    $disk = \Illuminate\Support\Facades\Storage::disk(\App\Support\Brand\BrandStorage::DISK_LUXVANT_PUBLIC);
    if (! $disk->exists($path)) {
        abort(404);
    }

    $root = realpath($disk->path('')) ?: $disk->path('');
    $full = realpath($disk->path($path));
    if ($full === false || ! str_starts_with(str_replace('\\', '/', $full), rtrim(str_replace('\\', '/', $root), '/').'/')) {
        abort(404);
    }

    return response()->file($full, ['Cache-Control' => 'public, max-age=604800']);
})->where('path', '.+')->name('storage.luxvant');

Route::get('{any}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');
