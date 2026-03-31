<?php

namespace OnaOnbir\Subscription\Exceptions;

use Exception;

class FeatureLimitExceededException extends Exception
{
    public function __construct(
        public readonly string $featureCode,
        public readonly int $currentUsage,
        public readonly int $limit,
        public readonly int $requestedAmount,
    ) {
        parent::__construct(
            "Feature [{$featureCode}] limit exceeded: {$currentUsage}/{$limit} (requested: {$requestedAmount})"
        );
    }
}
