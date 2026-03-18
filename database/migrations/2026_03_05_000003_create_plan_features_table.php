<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscription.tables.plan_features', 'plan_features'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained(config('subscription.tables.plans', 'plans'))->cascadeOnDelete();
            $table->foreignUlid('feature_id')->constrained(config('subscription.tables.features', 'features'))->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->json('overage_prices')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.plan_features', 'plan_features'));
    }
};
