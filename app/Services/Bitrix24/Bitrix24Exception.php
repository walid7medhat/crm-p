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
}
