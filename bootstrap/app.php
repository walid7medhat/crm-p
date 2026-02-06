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
       // Exclude broadcasting/auth from CSRF since we use JWT auth
       $middleware->validateCsrfTokens(except: [
           'broadcasting/auth',
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
                    
                    
        $schedule->command('activities:send-reminders')->everyMinute();
   
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
