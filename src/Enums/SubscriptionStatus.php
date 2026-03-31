<?php

namespace OnaOnbir\Subscription\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /**
     * @return array<int, self>
     */
    public static function activeStatuses(): array
    {
        return [self::Active, self::Trialing, self::PastDue];
    }
}
