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

    ]);
       // Exclude broadcasting/auth from CSRF since we use JWT auth
       $middleware->validateCsrfTokens(except: [
           'broadcasting/auth',
           'api/webhook/facebook', 
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

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return ApiResponse::error('Resource not found', 404);
            }

            return ApiResponse::error(
                $e->getMessage(),
                method_exists($e,'getStatusCode') ? $e->getStatusCode() : 500
            );
        });
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
            // $schedule->command('leads:check-revert')
            //         ->everyMinute()
            //         ->withoutOverlapping()
            //         ->appendOutputTo(storage_path('logs/lead-revert.log'));
                    
                    
        $schedule->command('activities:send-reminders')->everyMinute();
        $schedule->command('leads:score')
                ->everyTenMinutes()
                ->withoutOverlapping()
                ->runInBackground();
         $schedule->command('meta:refresh-app-token')
                 ->cron('0 0 */60 * *') 
                 ->withoutOverlapping()
                 ->runInBackground();
        $schedule->command('leads:score')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
            $schedule->command('attendance:sync')->everyFiveMinutes();
        $schedule->command('leads:auto-assign --scheduled-tick')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lead-auto-assign.log'));
        $schedule->command('leads:realtime-assign')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/lead-realtime-assign.log'));
        $schedule->command('leads:recover-stuck --sync')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        $schedule->command('leads:sla-escalation')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        $schedule->command('lead-assignment:refresh-performance')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
        $schedule->command('sales-intelligence:recalculate-scores')
            ->dailyAt('02:15')
            ->withoutOverlapping()
            ->runInBackground();
      $schedule->command('announcements:send-notifications')
         ->dailyAt('08:00')
         ->timezone('Asia/Dubai');
       
    $schedule->command('passport:check-expiry --days=30')
            ->dailyAt('09:30')
            ->timezone('Asia/Dubai')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/passport-expiry.log'));
    
    $schedule->command('passport:check-expiry --days=7')
            ->everySixHours()
            ->timezone('Asia/Dubai')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/passport-expiry-urgent.log'));
    $schedule->command('interviews:send-reminders --hours=24')
    ->dailyAt('09:00')
    ->timezone('Asia/Dubai');

    $schedule->command('interviews:send-reminders --hours=1')
        ->everyMinute()
        ->timezone('Asia/Dubai');
        
            // $schedule->command('bitrix:sync-leads')
            //     ->everyFifteenMinutes();
                // ->withoutOverlapping();
                // ->withoutOverlapping()
                // ->runInBackground();

        // ==================== TEST COMMANDS ====================
        // $schedule->command('activities:send-reminders --timeframe=today --test')
        //     ->dailyAt('10:00')
        //     ->description('Test activity reminders at 10:00 AM (UAE Time)');
            
        // // Test leads command
        // $schedule->command('leads:check-revert --test')
        //     ->dailyAt('11:00')
        //     ->description('Test leads revert at 11:00 AM (UAE Time)');


        })
      
    ->create();
