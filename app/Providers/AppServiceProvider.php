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
use App\Observers\ActivityObserver;
use App\Observers\DealAssignmentLearningObserver;
use App\Observers\LeadFirstContactObserver;
use App\Observers\SalesIntelligenceDealObserver;
use App\Observers\SalesIntelligenceLeadActivityObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceDealObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceLeadActivityObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceLeadCommentObserver;
use App\Observers\AiSalesIntelligence\AiSalesIntelligenceLeadObserver;
use App\Observers\LeadCommentObserver;
use App\Observers\LeadObserver;
use App\Support\InfrastructureBootstrap;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        InfrastructureBootstrap::boot($this->app);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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


    }
}
