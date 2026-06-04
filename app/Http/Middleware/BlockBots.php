<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockBots
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // استثناء السوبر أدمن والمستخدم 30
        $isExempt = $user && ($user->hasRole('super_admin') || $user->id == 30);
        if ($isExempt) {
            return $next($request);
        }

        // فحص حالة الحساب
        if ($user && $user->status != 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Account inactive');
        }

        // فحص User-Agent
        $agent = strtolower($request->header('User-Agent') ?? '');
        $botKeywords = ['curl', 'python', 'scrapy', 'wget', 'perl', 'ruby', 'java/', 'http-client'];
        
        if ($agent === '' || str_contains($agent, 'bot')) {
            abort(403, 'Bots not allowed');
        }
        
        foreach ($botKeywords as $keyword) {
            if (str_contains($agent, $keyword)) {
                abort(403, 'Bots not allowed');
            }
        }

        // --------------------------------------
        // 1. المعدل العام (للصفحات العادية)
        // --------------------------------------
        $key = 'hits_' . ($user?->id ?? $request->ip());
        $count = cache()->get($key, 0);
        $count++;
        cache()->put($key, $count, now()->addSeconds(60));

        if ($count > 120) { // 120 طلب لأي صفحة في الدقيقة (أقل من طلبين في الثانية)
            if ($user) Auth::logout();
            abort(429, 'Too many requests');
        }

        // --------------------------------------
        // 2. اكتشاف إساءة استخدام endpoint (مختلف حسب النوع)
        // --------------------------------------
        $path = $request->path();
        $method = $request->method();
        
        // تحديد حدود مختلفة حسب نوع الـ endpoint
        $limits = [
            'write' => 20,   // عمليات الكتابة (POST, PUT, DELETE): 20 في الدقيقة
            'read' => 100,   // عمليات القراءة (GET): 100 في الدقيقة
            'auth' => 10,    // عمليات تسجيل الدخول: 10 في الدقيقة
        ];
        
        // تحديد أي نوع ينتمي إليه هذا الـ endpoint
        $isWriteRequest = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
        $isAuthRequest = str_contains($path, 'login') || str_contains($path, 'register');
        
        if ($isAuthRequest) {
            $limit = $limits['auth'];
        } elseif ($isWriteRequest) {
            $limit = $limits['write'];
        } else {
            $limit = $limits['read'];
        }
        
        $routeKey = 'route_hits_' . ($user?->id ?? $request->ip()) . ':' . $path . ':' . $method;
        $routeCount = cache()->get($routeKey, 0);
        $routeCount++;
        
        // مدة أقل للعمليات المتكررة (30 ثانية بدلاً من 60)
        $duration = $isAuthRequest ? 60 * 5 : 60; // Auth: 5 دقائق، غيره: دقيقة
        cache()->put($routeKey, $routeCount, now()->addSeconds($duration));
        
        if ($routeCount > $limit) {
            // ⚠️ لا تقم بحظر المستخدم نهائياً! فقط ارفض الطلب مؤقتاً
            // لو تريد حظراً مؤقتاً (مثلاً 5 دقائق):
            $blockKey = 'temp_block_' . ($user?->id ?? $request->ip());
            cache()->put($blockKey, true, now()->addMinutes(5));
            
            abort(429, "Rate limit exceeded for this action. Please wait {$duration} seconds.");
        }
        
        // فحص الحظر المؤقت
        $tempBlockKey = 'temp_block_' . ($user?->id ?? $request->ip());
        if (cache()->get($tempBlockKey)) {
            abort(429, 'You are temporarily blocked. Please try again later.');
        }

        // --------------------------------------
        // 3. كشف الاندفاع (Burst) - حماية ضد الـ DoS فقط
        // --------------------------------------
        $burstKey = 'burst_' . ($user?->id ?? $request->ip());
        $burstCount = cache()->get($burstKey, 0);
        $burstCount++;
        
        if ($burstCount == 1) {
            cache()->put($burstKey, $burstCount, now()->addSeconds(10));
        } else {
            cache()->put($burstKey, $burstCount, now()->addSeconds(10));
        }
        
        // 200 طلب في 10 ثواني = 20 طلب في الثانية (هذا DoS حقيقي، وليس مستخدم عادي)
        if ($burstCount > 200) {
            // حتى هنا لا تحظر الحساب نهائياً، فقط حظر مؤقت
            $tempBlockKey = 'temp_block_' . ($user?->id ?? $request->ip());
            cache()->put($tempBlockKey, true, now()->addMinutes(15));
            
            if ($user) Auth::logout();
            abort(429, 'Rate limit exceeded. Too many requests in a short time.');
        }

        return $next($request);
    }
}