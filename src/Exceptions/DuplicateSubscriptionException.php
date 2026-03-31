<?php

namespace OnaOnbir\Subscription\Exceptions;

use OnaOnbir\Subscription\Models\Plan;
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
