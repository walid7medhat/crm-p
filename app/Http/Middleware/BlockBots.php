<?php 
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockBots
{
    public function handle(Request $request, Closure $next)
    {
        $user=auth()->check()?auth()->user():null;
        // 1. Block User (ده أهم جزء عندك)
        if (auth()->check() && auth()->user()->force_block) {
            $user->update(['status'=>'blocked']);
            Auth::logout();
            abort(403, 'Blocked');
        }

        // 2. User-Agent check (البوتات)
        $agent = strtolower($request->header('User-Agent') ?? '');

        if (
            str_contains($agent, 'bot') ||
            str_contains($agent, 'curl') ||
            str_contains($agent, 'python') ||
            str_contains($agent, 'scrapy')
        ) {
            abort(403, 'Bots not allowed');
        }

        return $next($request);
    }
}