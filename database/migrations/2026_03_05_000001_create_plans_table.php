<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscription.tables.plans', 'plans'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->json('slug');
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('prices');
            $table->string('billing_interval');
            $table->integer('trial_days')->default(0);
            $table->integer('grace_period_days')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.plans', 'plans'));
    }
};
