<?php

namespace App\Console\Commands;

use App\Services\DailyMotivation\DailyMotivationService;
use Illuminate\Console\Command;

class SendDailyMotivation extends Command
{
    protected $signature = 'motivation:send';

    protected $description = 'Send today\'s Daily Edge message to active users. Does nothing while delivery is stopped, and waits until 9:00 AM Dubai.';

    public function handle(DailyMotivationService $service): int
    {
        $result = $service->deliverToday();
        $this->info('Daily Edge '.$result['status'].'. Sent '.$result['sent'].'.');

        return self::SUCCESS;
    }
}
