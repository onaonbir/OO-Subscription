<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscription.tables.features', 'features'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->json('slug');
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('type');
            $table->boolean('resettable')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.features', 'features'));
    }
};
