<?php

namespace App\Subscription\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';
}
