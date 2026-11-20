<?php

namespace App\Jobs;

use App\Services\Security\ForeignAccessMonitorService;
use App\Support\Brand\Concerns\BrandAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued GeoIP evaluation for foreign CRM access.
 * Runs on the async queue (database/redis) — requires a queue worker.
 * Exceptions propagate for retries; failed() records permanent failures.
 */
class EvaluateForeignAccessJob implements ShouldQueue
{
    use BrandAwareJob;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public int $timeout = 30;

    public function __construct(
        public int $userId,
        public string $ip,
        string $brandKey = 'oia',
        public ?string $userAgent = null,
        public ?string $sessionKey = null,
        public string $trigger = 'login',
        public ?string $previousCountryCode = null,
    ) {
        // Capture host brand at dispatch; prefer the explicit argument.
        $this->initializeBrandContext();
        if ($brandKey !== '') {
            $this->brandKey = $brandKey;
        }

        $this->onQueue((string) config('foreign_access.queue', 'default'));
    }

    public function handle(ForeignAccessMonitorService $monitor): void
    {
        $this->runWithBrandContext(function () use ($monitor) {
            $monitor->evaluate(
                $this->userId,
                $this->ip,
                $this->brandKey,
                $this->userAgent,
                $this->sessionKey,
                $this->trigger,
                $this->previousCountryCode,
            );
        });
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('EvaluateForeignAccessJob permanently failed', [
            'user_id' => $this->userId,
            'ip' => $this->ip,
            'brand_key' => $this->brandKey,
            'trigger' => $this->trigger,
            'session_key' => $this->sessionKey,
            'error' => $exception?->getMessage(),
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
