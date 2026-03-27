<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'merchant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('merchant_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'merchant_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $row) {
                if (!Schema::hasColumn('orders', 'address_snapshot')) {
                    $row->text('address_snapshot')->nullable();
                }
                if (!Schema::hasColumn('orders', 'order_type')) {
                    $row->string('order_type')->default('delivery');
                }
                if (!Schema::hasColumn('orders', 'subtotal')) {
                    $row->decimal('subtotal', 15, 2)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_fee')) {
                    $row->decimal('delivery_fee', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('orders', 'tax_amount')) {
                    $row->decimal('tax_amount', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('orders', 'coupon_discount')) {
                    $row->decimal('coupon_discount', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('orders', 'coupon_code')) {
                    $row->string('coupon_code')->nullable();
                }
                if (!Schema::hasColumn('orders', 'platform_fee')) {
                    $row->decimal('platform_fee', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('orders', 'packaging_fee')) {
                    $row->decimal('packaging_fee', 15, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
    }
};
