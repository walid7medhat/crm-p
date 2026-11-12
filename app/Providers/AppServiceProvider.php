<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Events\MessageFailed;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\LeadHistory;
use App\Models\Listing;
use App\Observers\ActivityObserver;
use App\Observers\LeadMatchObserver;
use App\Observers\ListingMatchObserver;
use App\Observers\DealAssignmentLearningObserver;
use App\Observers\LeadFirstContactObserver;
use App\Observers\LiveWatchActivityObserver;
use App\Observers\LiveWatchLeadHistoryObserver;
use App\Observers\SalesIntelligenceDealObserver;
use App\Observers\SalesIntelligenceLeadActivityObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceDealObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceLeadActivityObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceLeadCommentObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceLeadObserver;
use App\Observers\LeadCommentObserver;
use App\Observers\LeadObserver;
use Spatie\Activitylog\Models\Activity;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandResolver;
use App\Support\InfrastructureBootstrap;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        InfrastructureBootstrap::boot($this->app);

        $this->app->singleton(BrandContext::class);
        $this->app->singleton(BrandResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // tymon/jwt-auth registers jwt.auth → Tymon\Authenticate during package boot and
        // overwrites bootstrap/app.php aliases. Re-assert after boot so our wrapper runs:
        // Tymon auth (unchanged OIA) → Luxvant-only brand allowlist.
        $this->app->booted(function () {
            $router = $this->app['router'];
            $router->aliasMiddleware('jwt.auth', \App\Http\Middleware\JwtAuthMiddleware::class);
            $router->aliasMiddleware('brand.access', \App\Http\Middleware\EnsureBrandAccess::class);
            $router->aliasMiddleware('brand.resolve', \App\Http\Middleware\ResolveBrandFromHost::class);
            $router->aliasMiddleware(
                'brand.block_luxvant_public',
                \App\Http\Middleware\BlockRiskyPublicOnLuxvant::class
            );
        });

        // Settings → Account Emails "send" endpoint. Own key: plain `throttle:N,1` shares one
        // per-user counter with other throttled routes (e.g. location pings every few seconds).
        \Illuminate\Support\Facades\RateLimiter::for('account-emails', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by('account-emails:' . ($request->user()?->id ?? $request->ip()));
        });

        // Isolated from other throttle:30,1 routes. Client cool-down is ~45–180s; 20/min
        // still allows reconnect bursts without raising the global API throttle.
        \Illuminate\Support\Facades\RateLimiter::for('location-tracking', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)
                ->by('location-tracking:' . ($request->user()?->id ?? $request->ip()));
        });

        // Live Watch: heartbeat ~30s; allow reconnect bursts without sharing other throttle buckets.
        \Illuminate\Support\Facades\RateLimiter::for('live-watch-heartbeat', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)
                ->by('live-watch-hb:' . ($request->user()?->id ?? $request->ip()));
        });
        \Illuminate\Support\Facades\RateLimiter::for('live-watch-navigation', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by('live-watch-nav:' . ($request->user()?->id ?? $request->ip()));
        });

    //       Event::listen(MessageSending::class, function ($event) {
    //     \Log::info('📤 Sending Email', [
    //         'to' => $event->message->getTo(),
    //         'subject' => $event->message->getSubject(),
    //     ]);
    // });
\Illuminate\Support\Facades\Response::macro('secureHeaders', function ($response) {
        return $response->header('Permissions-Policy', 'geolocation=(self)');
    });
    Event::listen(MessageFailed::class, function ($event) {
        \Log::error('❌ Mail Failed', [
            'to' => $event->message->getTo(),
            'subject' => $event->message->getSubject(),
            'error' => $event->exception->getMessage(),
        ]);
    });
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $base = config('app.frontend_url', config('app.url'));
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return $base.'/reset-password?token='.urlencode($token).'&email='.$email;
        });

        Deal::observe(SalesIntelligenceDealObserver::class);
        Deal::observe(AiSalesIntelligenceDealObserver::class);
        Deal::observe(DealAssignmentLearningObserver::class);
        Lead::observe(AiSalesIntelligenceLeadObserver::class);
        LeadActivity::observe(SalesIntelligenceLeadActivityObserver::class);
        LeadActivity::observe(AiSalesIntelligenceLeadActivityObserver::class);
        LeadActivity::observe(LeadFirstContactObserver::class);
        LeadComment::observe(AiSalesIntelligenceLeadCommentObserver::class);

            Lead::observe(LeadObserver::class);
            LeadComment::observe(LeadCommentObserver::class);
            LeadActivity::observe(ActivityObserver::class);

        // Match Studio: re-match leads and listings when they change.
        Lead::observe(LeadMatchObserver::class);
        Listing::observe(ListingMatchObserver::class);

        // Super Admin Live Watch: mirror existing history/activity into the timeline.
        LeadHistory::observe(LiveWatchLeadHistoryObserver::class);
        Activity::observe(LiveWatchActivityObserver::class);
    }
}
