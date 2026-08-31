<?php

namespace App\Console\Commands;

use App\Mail\BirthdayCelebrationMail;
use App\Models\User;
use App\Notifications\BirthdayColleagueNotification;
use App\Notifications\BirthdaySelfNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBirthdayCelebrations extends Command
{
    protected $signature = 'birthdays:celebrate';
    protected $description = "Celebrate today's birthdays: email the birthday user and notify other active users";

    public function handle()
    {
        $today = now();

        $birthdayUsers = User::where('status', 'active')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', $today->day)
            ->get();

        if ($birthdayUsers->isEmpty()) {
            $this->info('No birthdays today.');
            return 0;
        }

        foreach ($birthdayUsers as $birthdayUser) {
            try {
                $alreadyCelebrated = $birthdayUser->notifications()
                    ->where('type', BirthdaySelfNotification::class)
                    ->whereDate('created_at', $today->toDateString())
                    ->exists();

                if ($alreadyCelebrated) {
                    $this->info("Already celebrated today: {$birthdayUser->name}");
                    continue;
                }

                $recipientEmail = $birthdayUser->personal_email ?: $birthdayUser->email;
                Mail::to($recipientEmail)->send(new BirthdayCelebrationMail($birthdayUser));

                $birthdayUser->notify(new BirthdaySelfNotification());

                $colleagues = User::where('status', 'active')
                    ->where('id', '!=', $birthdayUser->id)
                    ->get();

                foreach ($colleagues as $colleague) {
                    $colleague->notify(new BirthdayColleagueNotification($birthdayUser));
                }

                $this->info("Celebrated birthday for {$birthdayUser->name} ({$colleagues->count()} colleagues notified)");
            } catch (\Exception $e) {
                Log::error("Failed to celebrate birthday for user {$birthdayUser->id}: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
