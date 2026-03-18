<?php

namespace App\Subscription\Enums;

enum FeatureType: string
{
    case Boolean = 'boolean';
    case Quantity = 'quantity';
    case Metered = 'metered';
}
