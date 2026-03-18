<?php

namespace App\Subscription\Exceptions;

use Exception;

class InvalidSubscriptionStateException extends Exception
{
    public function __construct(
        public readonly string $action,
        public readonly string $currentStatus,
    ) {
        parent::__construct(
            "Cannot {$action} subscription with status: {$currentStatus}"
        );
    }
}
