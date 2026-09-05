<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DocumentExpiryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckDocumentExpiry extends Command
{
    protected $signature = 'employees:check-document-expiry
                            {--days= : Override the configured days-before-expiry threshold for every document type}
                            {--test : Test mode - don\'t send actual notifications}';

    protected $description = 'Check labor card, Emirates ID, passport and residency (visa) expiry dates and notify HR before they expire, using the per-type thresholds set in HR Settings';

    protected int $defaultDays = 15;

    /**
     * document key => [EmployeeProfile date column, human label, EmployeeProfile number column or null, settings column]
     */
    protected array $documents = [
        'passport' => ['passport_expiry_date', 'Passport', 'passport_number', 'passport_days'],
        'labor_card' => ['labor_card_expiry_date', 'Labor Card', 'labor_card_number', 'labor_card_days'],
        'emirates_id' => ['emirates_id_expiry_date', 'Emirates ID', 'emirates_id_number', 'emirates_id_days'],
        'residency' => ['visa_validity', 'Residency Visa', null, 'residency_days'],
    ];

    public function handle()
    {
        $isTest = $this->option('test');
        $override = $this->option('days');
        $settings = DB::table('document_expiry_settings')->orderBy('id')->first();

        $today = Carbon::today();

        $hrUsers = User::role(['super_admin', 'hr'])->get();

        if ($hrUsers->isEmpty()) {
            $this->warn('No HR users found to notify.');
            return 1;
        }

        $tableData = [];
        $totalNotifications = 0;

        foreach ($this->documents as [$column, $label, $numberColumn, $settingsColumn]) {
            $daysThreshold = $override !== null
                ? (int) $override
                : (int) ($settings->{$settingsColumn} ?? $this->defaultDays);

            $expiryThreshold = $today->copy()->addDays($daysThreshold);

            $this->info("Checking {$label} expiring within {$daysThreshold} days (threshold: {$expiryThreshold->toDateString()})...");

            $users = User::whereHas('employeeProfile', function ($query) use ($column, $expiryThreshold) {
                $query->whereNotNull($column)
                    ->whereDate($column, '=', $expiryThreshold);
            })->with('employeeProfile')->get();

            foreach ($users as $user) {
                $expiryDate = Carbon::parse($user->employeeProfile->{$column});
                $daysLeft = $today->diffInDays($expiryDate);
                $number = $numberColumn ? ($user->employeeProfile->{$numberColumn} ?? null) : null;

                $tableData[] = [
                    'Name' => $user->name,
                    'Document' => $label,
                    'Number' => $number ?? 'N/A',
                    'Expiry Date' => $expiryDate->format('Y-m-d'),
                    'Days Left' => $daysLeft,
                ];

                $this->line("  {$user->name} - {$label} expires in {$daysLeft} days ({$expiryDate->format('Y-m-d')})");

                if (!$isTest) {
                    foreach ($hrUsers as $hrUser) {
                        try {
                            $hrUser->notify(new DocumentExpiryNotification(
                                $user,
                                $label,
                                $number,
                                $expiryDate,
                                $daysLeft
                            ));
                            $totalNotifications++;
                        } catch (\Exception $e) {
                            Log::error("Failed to send document expiry notification: {$e->getMessage()}");
                        }
                    }
                }
            }
        }

        if (empty($tableData)) {
            $this->info('No documents expiring at their configured thresholds.');
            return 0;
        }

        $this->newLine();
        $this->table(['Name', 'Document', 'Number', 'Expiry Date', 'Days Left'], $tableData);

        $this->newLine();
        $this->info('Done!');
        $this->info("Notifications sent: {$totalNotifications}");
        $this->info('HR users notified: ' . $hrUsers->count());

        Log::info('Document expiry check completed', [
            'documents_affected' => count($tableData),
            'notifications_sent' => $totalNotifications,
            'override_days' => $override,
            'test_mode' => $isTest,
        ]);

        return 0;
    }
}
