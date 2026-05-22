<?php

namespace App\Services\Bitrix24;

class Bitrix24Exception extends \RuntimeException
{
    public ?string $errorDescription;

    public function __construct(string $message, int $httpStatus = 0, ?string $errorDescription = null)
    {
        parent::__construct($message, $httpStatus);
        $this->errorDescription = $errorDescription;
    }

    public function isNotFound(): bool
    {
        $desc = strtolower((string) $this->errorDescription);
        return $desc === 'not found' || str_contains($desc, 'not found');
    }

    public function isRateLimited(): bool
    {
        $msg = strtolower($this->getMessage() . ' ' . (string) $this->errorDescription);

        return str_contains($msg, 'operation_time_limit')
            || str_contains($msg, 'query_limit_exceeded')
            || str_contains($msg, 'rate limit')
            || str_contains($msg, 'rate-limited')
            || str_contains($msg, 'too many')
            || str_contains($msg, 'http 429')
            || $this->getCode() === 429
            || $this->getCode() === 503;
    }

    public function isTransient(): bool
    {
        if ($this->isRateLimited()) {
            return true;
        }

        $msg = strtolower($this->getMessage());

        return str_contains($msg, 'timeout')
            || str_contains($msg, 'connection')
            || str_contains($msg, 'temporarily')
            || str_contains($msg, 'failed after')
            || $this->getCode() >= 500;
    }
}
