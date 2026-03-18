<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscription.tables.feature_usages', 'feature_usages'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->morphs('subscribable');
            $table->string('feature_code');
            $table->integer('used')->default(0);
            $table->timestamp('resets_at')->nullable();
            $table->timestamps();

            $table->unique(['subscribable_type', 'subscribable_id', 'feature_code'], 'feature_usages_subscribable_feature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.feature_usages', 'feature_usages'));
    }
};
