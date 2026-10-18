<?php

namespace App\Services\LeadScan;

use RuntimeException;

class LeadScanException extends RuntimeException
{
    public function __construct(
        public readonly string $kind,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $kind);
    }
}
