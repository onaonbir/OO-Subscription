<?php

namespace OnaOnbir\Subscription\Enums;

use Carbon\Carbon;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    public function addToDate(Carbon $date): ?Carbon
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonth(),
            self::Yearly => $date->copy()->addYear(),
            self::Lifetime => null,
        };
    }
}
