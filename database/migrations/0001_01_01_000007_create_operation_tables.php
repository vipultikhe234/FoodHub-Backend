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
        if (!Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('address_line');
                $table->string('landmark')->nullable();
                $table->string('city');
                $table->string('state')->nullable();
                $table->string('pincode')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('type')->default('home'); // home, work, other
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->enum('type', ['fixed', 'percentage']);
                $table->decimal('value', 10, 2);
                $table->decimal('min_order_amount', 10, 2)->default(0);
                $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
                $table->dateTime('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

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
                $row->string('order_type')->default('delivery'); // delivery, pickup
                
                // Financial breakdown snapshot (Atomic Checkout Process)
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

        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $row) {
                $row->id();
                $row->foreignId('order_id')->constrained()->cascadeOnDelete();
                $row->foreignId('product_id')->constrained()->cascadeOnDelete();
                $row->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
                $row->integer('quantity');
                $row->decimal('price', 15, 2); // Snapshot of price at time of order
                $row->decimal('total', 15, 2);
                $row->json('options_snapshot')->nullable(); // For add-ons, etc.
                $row->timestamps();
            });
        }

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->string('payment_id')->nullable();
                $table->string('payment_method')->default('card');
                $table->decimal('amount', 10, 2);
                $table->string('status')->default('pending');
                $table->json('payment_response')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('merchant_other_charges')) {
            Schema::create('merchant_other_charges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->decimal('delivery_charge', 10, 2)->default(0);
                $table->decimal('packaging_charge', 10, 2)->default(0);
                $table->decimal('platform_fee', 10, 2)->default(0);
                $table->decimal('delivery_charge_tax', 5, 2)->default(0);
                $table->decimal('packaging_charge_tax', 5, 2)->default(0);
                $table->decimal('platform_fee_tax', 5, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity')->default(1);
                $table->timestamps();
                
                $table->index(['user_id', 'product_variant_id']);
            });
        }

        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $row) {
                $row->id();
                $row->foreignId('user_id')->constrained()->cascadeOnDelete();
                $row->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $row->foreignId('order_id')->constrained()->cascadeOnDelete();
                $row->integer('rating');
                $row->text('review')->nullable();
                $row->timestamps();
            });
        }

        if (!Schema::hasTable('notification_histories')) {
            Schema::create('notification_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('message');
                $table->string('type')->nullable();
                $table->json('data')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
        Schema::dropIfExists('notification_histories');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('merchant_other_charges');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
    }
};
