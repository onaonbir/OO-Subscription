<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscription.tables.subscribable_features', 'subscribable_features'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->morphs('subscribable');
            $table->foreignUlid('feature_id')->constrained(config('subscription.tables.features', 'features'))->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->json('overage_prices')->nullable();
            $table->string('billing_interval')->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.subscribable_features', 'subscribable_features'));
    }
};
