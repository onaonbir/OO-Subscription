<?php

namespace OnaOnbir\Subscription\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    public function addToDate(\Carbon\Carbon $date): ?\Carbon\Carbon
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonth(),
            self::Yearly => $date->copy()->addYear(),
            self::Lifetime => null,
        };
    }
}
