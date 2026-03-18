<?php

namespace App\Subscription\Exceptions;

use App\Subscription\Models\Plan;
use Exception;

class DuplicateSubscriptionException extends Exception
{
    public function __construct(
        public readonly Plan $plan,
    ) {
        parent::__construct(
            "An active subscription already exists for plan [{$plan->id}]."
        );
    }
}
