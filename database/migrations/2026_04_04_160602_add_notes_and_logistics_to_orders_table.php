<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'notes')) {
                $table->text('notes')->nullable()->after('order_type');
            }
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number')->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key')->unique()->nullable()->after('order_number');
            }
            if (!Schema::hasColumn('orders', 'rider_id')) {
                $table->foreignId('rider_id')->nullable()->after('merchant_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('rider_id')->constrained('coupons')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'estimated_delivery_time')) {
                $table->datetime('estimated_delivery_time')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('orders', 'actual_delivery_time')) {
                $table->datetime('actual_delivery_time')->nullable()->after('estimated_delivery_time');
            }
            if (!Schema::hasColumn('orders', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('actual_delivery_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'notes', 
                'order_number', 
                'idempotency_key', 
                'rider_id', 
                'coupon_id', 
                'estimated_delivery_time', 
                'actual_delivery_time', 
                'cancellation_reason'
            ]);
        });
    }
};
