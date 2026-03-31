<?php

namespace OnaOnbir\Subscription\Contracts;

use OnaOnbir\Subscription\Models\Subscription;

interface PaymentGateway
{
    public function create(Subscription $subscription): array;

    public function cancel(Subscription $subscription): bool;

    public function renew(Subscription $subscription): array;

    public function changePlan(Subscription $subscription, array $newPlanData): array;
}
