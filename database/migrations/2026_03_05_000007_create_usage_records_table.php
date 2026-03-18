<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscription.tables.usage_records', 'usage_records'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->morphs('subscribable');
            $table->string('feature_code');
            $table->integer('amount');
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.usage_records', 'usage_records'));
    }
};
