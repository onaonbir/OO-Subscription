<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptionsTable = config('subscription.tables.subscriptions', 'subscriptions');
        $featureUsagesTable = config('subscription.tables.feature_usages', 'feature_usages');
        $usageRecordsTable = config('subscription.tables.usage_records', 'usage_records');

        Schema::table($subscriptionsTable, function (Blueprint $table) {
            $table->index('status');
            $table->index(['subscribable_type', 'subscribable_id', 'status'], 'subscriptions_subscribable_status_index');
        });

        Schema::table($featureUsagesTable, function (Blueprint $table) {
            $table->unsignedInteger('used')->default(0)->change();
            $table->index('resets_at');
        });

        Schema::table($usageRecordsTable, function (Blueprint $table) {
            $table->index(
                ['subscribable_type', 'subscribable_id', 'feature_code', 'recorded_at'],
                'usage_records_subscribable_feature_recorded_index'
            );
        });
    }

    public function down(): void
    {
        $subscriptionsTable = config('subscription.tables.subscriptions', 'subscriptions');
        $featureUsagesTable = config('subscription.tables.feature_usages', 'feature_usages');
        $usageRecordsTable = config('subscription.tables.usage_records', 'usage_records');

        Schema::table($subscriptionsTable, function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex('subscriptions_subscribable_status_index');
        });

        Schema::table($featureUsagesTable, function (Blueprint $table) {
            $table->integer('used')->default(0)->change();
            $table->dropIndex(['resets_at']);
        });

        Schema::table($usageRecordsTable, function (Blueprint $table) {
            $table->dropIndex('usage_records_subscribable_feature_recorded_index');
        });
    }
};
