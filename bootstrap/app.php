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
            $schedule->command('leads:check-revert')
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/lead-revert.log'));
               $schedule->command('activities:send-reminders --timeframe=upcoming')
            ->everyTwoHours()
            ->description('Send reminders for activities in next 24 hours (every 2 hours, UAE Time)');
            
        // Send morning reminders at 9 AM
        $schedule->command('activities:send-reminders --timeframe=today')
            ->dailyAt('09:00')
            ->description('Send morning reminders at 9:00 AM (UAE Time)');
            
        // Send afternoon reminders at 2 PM
        $schedule->command('activities:send-reminders --timeframe=today')
            ->dailyAt('14:00')
            ->description('Send afternoon reminders at 2:00 PM (UAE Time)');
            
        // Send reminders at 5 PM for tomorrow's activities
        $schedule->command('activities:send-reminders --timeframe=tomorrow')
            ->dailyAt('17:00')
            ->description('Send reminders for tomorrow\'s activities at 5:00 PM (UAE Time)');
            
        // Send overdue reminders every 6 hours
        $schedule->command('activities:send-reminders --timeframe=overdue')
            ->everySixHours()
            ->description('Send reminders for overdue activities (UAE Time)');
            
        // ==================== TEST COMMANDS ====================
        $schedule->command('activities:send-reminders --timeframe=today --test')
            ->dailyAt('10:00')
            ->description('Test activity reminders at 10:00 AM (UAE Time)');
            
        // Test leads command
        $schedule->command('leads:check-revert --test')
            ->dailyAt('11:00')
            ->description('Test leads revert at 11:00 AM (UAE Time)');


        })
      
    ->create();
