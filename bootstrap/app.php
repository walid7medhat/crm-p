<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Helpers\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use App\Http\Middleware\JwtAuthMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use App\Console\Commands\RefreshMetaAppToken;
use App\Http\Middleware\BlockBots;
use App\Http\Middleware\BlockRiskyPublicOnLuxvant;
use App\Http\Middleware\EnsureBrandAccess;
use App\Http\Middleware\ResolveBrandFromHost;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
   ->withMiddleware(function (Middleware $middleware) {
       
       $middleware->alias([
        'role' => RoleMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,
        'jwt.auth' => JwtAuthMiddleware::class,
                'block.bots' => BlockBots::class,
        'log.usage' => \App\Http\Middleware\LogEndpointUsage::class,
        'trusted.referer' => \App\Http\Middleware\RequireTrustedReferer::class,
        'chat.no-switcher' => \App\Http\Middleware\BlockChatForSalesSwitcher::class,
        'brand.resolve' => ResolveBrandFromHost::class,
        'brand.access' => EnsureBrandAccess::class,
        'brand.block_luxvant_public' => BlockRiskyPublicOnLuxvant::class,
        'maintenance' => \App\Http\Middleware\EnsureNotInMaintenance::class,

    ]);
       // Exclude broadcasting/auth from CSRF since we use JWT auth
       $middleware->validateCsrfTokens(except: [
           'broadcasting/auth',
           'api/webhook/facebook', 
       ]);

       $middleware->trustProxies(at: '*');

        // Host → brand context for every web + API request (before controllers).
        $middleware->append(ResolveBrandFromHost::class);

        // Global maintenance (after brand resolve). HTTP-only; queues keep running.
        $middleware->append(\App\Http\Middleware\EnsureNotInMaintenance::class);

        $middleware->api(append: [
            \App\Http\Middleware\RequireTrustedReferer::class,
        ]);
        })

   ->withExceptions(function ($exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return ApiResponse::error('Validation error', 422, $e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthorized', 401);
            }

            // Route-model binding misses arrive wrapped in a NotFoundHttpException whose message is
            // "No query results for model [App\Models\…] 290" — never show that to users.
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return ApiResponse::error('This record no longer exists. Please refresh the page and try again.', 404);
            }

            $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            // Always log internals server-side; never leak SQL/paths/stack to API clients in production.
            if ($status >= 500) {
                \Illuminate\Support\Facades\Log::error('Unhandled API exception', [
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'path' => $request->path(),
                ]);
            }

            if (config('app.debug')) {
                return ApiResponse::error($e->getMessage() !== '' ? $e->getMessage() : 'Something went wrong.', $status);
            }

            $safe = match (true) {
                $status === 404 => 'Not found.',
                $status === 403 => 'Forbidden.',
                $status === 401 => 'Unauthorized',
                $status === 429 => 'Too many attempts. Please try again later.',
                default => 'Something went wrong.',
            };

            return ApiResponse::error($safe, $status);
        });
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // brand:run applies BrandJobPolicy (per_brand / oia_only / global_shared).
        $schedule->command('brand:run', ['live-watch:cleanup'])
            ->everyMinute()->name('brand:live-watch:cleanup')->withoutOverlapping()->runInBackground();
        $schedule->command('brand:run', ['activities:send-reminders'])
            ->everyMinute()->name('brand:activities:send-reminders');
        $schedule->command('brand:run', ['marketing:send-reminders'])
            ->twiceDaily(9, 15)->timezone('Asia/Dubai')->name('brand:marketing:send-reminders')->withoutOverlapping();
        $schedule->command('brand:run', ['birthdays:celebrate'])
            ->dailyAt('08:00')->name('brand:birthdays:celebrate');
        $schedule->command('brand:run', ['matches:scan-quiet'])
            ->dailyAt('07:45')->timezone('Asia/Dubai')->name('brand:matches:scan-quiet')->withoutOverlapping()->runInBackground();
        $schedule->command('brand:run', ['evaluations:check-due'])
            ->dailyAt('08:15')->name('brand:evaluations:check-due');
        $schedule->command('brand:run', ['leads:score'])
            ->everyTenMinutes()->name('brand:leads:score')->withoutOverlapping()->runInBackground();
        $schedule->command('brand:run', ['meta:refresh-app-token'])
            ->cron('0 0 */60 * *')->name('brand:meta:refresh-app-token')->withoutOverlapping()->runInBackground();
        $schedule->command('brand:run', ['attendance:sync'])
            ->everyFiveMinutes()->name('brand:attendance:sync');
        $schedule->command('brand:run', ['attendance:notify-absent'])
            ->dailyAt('11:00')->timezone('Asia/Dubai')->name('brand:attendance:notify-absent');
        $schedule->command('brand:run', ['leads:auto-assign', '--option=scheduled-tick=1'])
            ->everyMinute()->name('brand:leads:auto-assign')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lead-auto-assign.log'));
        $schedule->command('brand:run', ['leads:realtime-assign'])
            ->everyMinute()->name('brand:leads:realtime-assign')->withoutOverlapping()->runInBackground()
            ->appendOutputTo(storage_path('logs/lead-realtime-assign.log'));
        $schedule->command('brand:run', ['leads:merge-pool-duplicates'])
            ->everyThirtyMinutes()->timezone('Asia/Dubai')->name('brand:leads:merge-pool-duplicates')
            ->withoutOverlapping(25)->onOneServer();
        // Property Finder: OIA-only when enabled (BrandJobPolicy).
        // $schedule->command('brand:run', ['propertyfinder:sync-leads', '--option=minutes=60'])->everyTenMinutes()...
        $schedule->command('brand:run', ['leads:recover-stuck', '--option=sync=1'])
            ->everyFiveMinutes()->name('brand:leads:recover-stuck')->withoutOverlapping();
        $schedule->command('brand:run', ['leads:sla-escalation'])
            ->everyFiveMinutes()->name('brand:leads:sla-escalation')->withoutOverlapping();
        $schedule->command('brand:run', ['lead-assignment:refresh-performance'])
            ->hourly()->name('brand:lead-assignment:refresh-performance')->withoutOverlapping()->runInBackground();
        $schedule->command('brand:run', ['sales-intelligence:recalculate-scores'])
            ->dailyAt('02:15')->name('brand:sales-intelligence:recalculate-scores')->withoutOverlapping()->runInBackground();
        $schedule->command('brand:run', ['ai-sales-intelligence:recalculate'])
            ->dailyAt('02:30')->timezone('Asia/Dubai')->name('brand:ai-sales-intelligence:recalculate')
            ->withoutOverlapping()->runInBackground();
        $schedule->command('brand:run', ['announcements:send-notifications'])
            ->dailyAt('08:00')->timezone('Asia/Dubai')->name('brand:announcements:send-notifications');
        $schedule->command('brand:run', ['passport:check-expiry', '--option=days=30'])
            ->dailyAt('09:30')->timezone('Asia/Dubai')->name('brand:passport:check-expiry-30')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/passport-expiry.log'));
        $schedule->command('brand:run', ['passport:check-expiry', '--option=days=7'])
            ->everySixHours()->timezone('Asia/Dubai')->name('brand:passport:check-expiry-7')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/passport-expiry-urgent.log'));
        $schedule->command('brand:run', ['interviews:send-reminders', '--option=hours=24'])
            ->dailyAt('09:00')->timezone('Asia/Dubai')->name('brand:interviews:send-reminders-24');
        $schedule->command('brand:run', ['motivation:send'])
            ->dailyAt(config('daily_motivation.send_time', '09:00'))
            ->timezone(config('daily_motivation.timezone', 'Asia/Dubai'))
            ->name('brand:motivation:send')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/daily-motivation.log'));
        $schedule->command('brand:run', ['interviews:send-reminders', '--option=hours=1'])
            ->everyMinute()->timezone('Asia/Dubai')->name('brand:interviews:send-reminders-1');
        $schedule->command('brand:run', ['leave:accrue-annual'])
            ->dailyAt('01:00')->timezone('Asia/Dubai')->name('brand:leave:accrue-annual')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/leave-accrue-annual.log'));
        $schedule->command('brand:run', ['employees:check-document-expiry'])
            ->dailyAt('09:15')->timezone('Asia/Dubai')->name('brand:employees:check-document-expiry')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/document-expiry.log'));
        })
      
    ->create();
