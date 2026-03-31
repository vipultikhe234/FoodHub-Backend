<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $row) {
                $row->id();
                $row->foreignId('user_id')->constrained()->cascadeOnDelete();
                $row->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $row->decimal('total_price', 15, 2);
                $row->foreignId('address_id')->nullable()->constrained('user_addresses')->nullOnDelete();
                $row->text('address_snapshot')->nullable();
                $row->string('payment_method')->default('COD');
                $row->string('payment_status')->default('pending');
                $row->string('status')->default('pending');
                $row->string('order_type')->default('delivery');
                
                $row->decimal('subtotal', 15, 2)->nullable();
                $row->decimal('delivery_fee', 15, 2)->default(0);
                $row->decimal('tax_amount', 15, 2)->default(0);
                $row->decimal('coupon_discount', 15, 2)->default(0);
                $row->string('coupon_code')->nullable();
                $row->decimal('platform_fee', 15, 2)->default(0);
                $row->decimal('packaging_fee', 15, 2)->default(0);
                
                $row->timestamps();
                $row->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
