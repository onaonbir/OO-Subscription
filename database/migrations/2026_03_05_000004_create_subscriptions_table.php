<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscription.tables.subscriptions', 'subscriptions'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->morphs('subscribable');
            $table->foreignUlid('plan_id')->constrained(config('subscription.tables.plans', 'plans'));
            $table->json('plan_snapshot');
            $table->string('gateway')->nullable();
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->string('status');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancels_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('canceled_reason')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.subscriptions', 'subscriptions'));
    }
};
