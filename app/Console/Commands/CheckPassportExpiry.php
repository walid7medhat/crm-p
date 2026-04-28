<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PassportExpiryNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CheckPassportExpiry extends Command
{
    protected $signature = 'passport:check-expiry 
                            {--days=30 : Check passports expiring within X days}
                            {--send-email : Send email notifications}
                            {--test : Test mode - don\'t send actual emails}';
    
    protected $description = 'Check for expiring passports and notify HR managers';

    public function handle()
    {
        $daysThreshold = (int) $this->option('days');
        $isTest = $this->option('test');
        $sendEmail = $this->option('send-email');
        
        $this->info("🔍 Checking passports expiring within {$daysThreshold} days...");
        $this->info("📅 Today: " . now()->toDateString());
        
        $today = Carbon::today();
        $expiryThreshold = $today->copy()->addDays($daysThreshold);
        
        // جلب الموظفين الذين تنتهي صلاحية جوازاتهم خلال الفترة
        $users = User::whereHas('employeeProfile', function($query) use ($today, $expiryThreshold) {
            $query->whereNotNull('passport_expiry_date')
                  ->where('passport_expiry_date', '>=', $today)
                  ->where('passport_expiry_date', '<=', $expiryThreshold);
        })->with('employeeProfile')->get();
        
        if ($users->isEmpty()) {
            $this->info("✅ No passports expiring within {$daysThreshold} days.");
            return 0;
        }
        
        $this->info("\n📋 Found " . $users->count() . " employee(s) with expiring passports:\n");
        
        // جلب الـ HR Managers
        $hrManagers = User::role([ 'super_admin'])->get();
        
        if ($hrManagers->isEmpty()) {
            $this->warn("⚠️ No HR managers found to notify.");
            return 1;
        }
        
        $tableData = [];
        $totalNotifications = 0;
        
        foreach ($users as $user) {
            $expiryDate = Carbon::parse($user->employeeProfile->passport_expiry_date);
            $daysLeft = $today->diffInDays($expiryDate);
            
            // تحديد مستوى الإلحاح
            $urgency = $daysLeft <= 7 ? '🔴 URGENT' : ($daysLeft <= 30 ? '🟡 Warning' : '🟢 Normal');
            
            $tableData[] = [
                'Name' => $user->name,
                'Employee Code' => $user->employeeProfile->employee_code ?? 'N/A',
                'Passport No.' => $user->employeeProfile->passport_number ?? 'N/A',
                'Expiry Date' => $expiryDate->format('Y-m-d'),
                'Days Left' => $daysLeft,
                'Urgency' => $urgency,
            ];
            
            $this->line("  📌 {$user->name} - Passport expires in {$daysLeft} days ({$expiryDate->format('Y-m-d')})");
            
            if (!$isTest) {
                foreach ($hrManagers as $hrManager) {
                    try {
                        $hrManager->notify(new PassportExpiryNotification($user, $expiryDate, $daysLeft));
                        $totalNotifications++;
                    } catch (\Exception $e) {
                        Log::error("Failed to send passport expiry notification: {$e->getMessage()}");
                    }
                }
            }
        }
        
        $this->newLine();
        $this->table(['Name', 'Employee Code', 'Passport No.', 'Expiry Date', 'Days Left', 'Urgency'], $tableData);
        
        // إرسال إيميل تلخيصي لمدير HR (اختياري)
        if ($sendEmail && !$isTest && $hrManagers->isNotEmpty()) {
            $this->sendSummaryEmail($hrManagers->first(), $tableData, $daysThreshold);
        }
        
        $this->newLine();
        $this->info("✅ Done!");
        $this->info("📧 Notifications sent: {$totalNotifications}");
        $this->info("👥 HR Managers notified: " . $hrManagers->count());
        
        // تسجيل في الـ log
        Log::info("Passport expiry check completed", [
            'employees_affected' => $users->count(),
            'notifications_sent' => $totalNotifications,
            'days_threshold' => $daysThreshold,
            'test_mode' => $isTest
        ]);
        
        return 0;
    }
    
    /**
     * إرسال إيميل تلخيصي
     */
    private function sendSummaryEmail($hrManager, $expiringEmployees, $daysThreshold)
    {
        try {
            $subject = "📋 Passport Expiry Report - {$daysThreshold} Days Threshold";
            
            $html = view('emails.passport-expiry-summary', [
                'hrManager' => $hrManager,
                'employees' => $expiringEmployees,
                'daysThreshold' => $daysThreshold,
                'date' => now()->format('F j, Y'),
            ])->render();
            
            Mail::html($html, function ($message) use ($hrManager, $subject) {
                $message->to($hrManager->email, $hrManager->name)
                        ->subject($subject);
            });
            
            $this->info("📧 Summary email sent to: {$hrManager->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send summary email: {$e->getMessage()}");
        }
    }
}