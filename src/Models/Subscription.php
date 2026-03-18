<?php

namespace App\Subscription\Models;

use App\Subscription\Database\Factories\SubscriptionFactory;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Support\ModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_snapshot' => 'array',
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancels_at' => 'datetime',
            'canceled_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('subscription.tables.subscriptions', 'subscriptions');
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::plan());
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function isTrialing(): bool
    {
        return $this->status === SubscriptionStatus::Trialing;
    }

    public function isPastDue(): bool
    {
        return $this->status === SubscriptionStatus::PastDue;
    }

    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::Canceled;
    }

    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::Expired;
    }

    public function isValid(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trialing,
            SubscriptionStatus::PastDue,
        ]);
    }

    public function onTrial(): bool
    {
        return $this->isTrialing() && $this->trial_ends_at?->isFuture();
    }

    public function onGracePeriod(): bool
    {
        return $this->grace_ends_at?->isFuture() ?? false;
    }

    public function hasCancelScheduled(): bool
    {
        return $this->cancels_at !== null && $this->canceled_at === null;
    }

    public function isLifetime(): bool
    {
        return $this->ends_at === null && $this->isActive();
    }
}
