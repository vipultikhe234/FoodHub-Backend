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
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $row) {
                $row->id();
                $row->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
                $row->string('name');
                $row->string('image')->nullable();
                $row->boolean('status')->default(true);
                $row->timestamps();
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $row) {
                $row->id();
                $row->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $row->foreignId('category_id')->constrained()->cascadeOnDelete();
                $row->string('name');
                $row->text('description')->nullable();
                $row->decimal('price', 10, 2); // Base price
                $row->decimal('discount_price', 10, 2)->nullable();
                $row->string('image')->nullable();
                $row->integer('stock')->default(0);
                $row->boolean('is_veg')->default(false);
                $row->integer('spicy_level')->default(0); // 0: None, 1: Mild, 2: Medium, 3: Hot
                $row->integer('calories')->nullable();
                $row->integer('preparation_time')->nullable(); // in minutes
                $row->boolean('is_popular')->default(false);
                $row->boolean('is_recommended')->default(false);
                $row->boolean('is_new')->default(false);
                $row->decimal('tax_rate', 5, 2)->nullable(); // Override for specific items
                $row->boolean('has_variants')->default(false);
                $row->boolean('is_active')->default(true);
                $row->boolean('is_available')->default(true);
                $row->softDeletes();
                $row->timestamps();
            });
        }

        if (!Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('quantity')->nullable();
                $table->decimal('mrp_price', 10, 2)->nullable();
                $table->decimal('price', 10, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                
                $table->unique(['product_id', 'quantity'], 'unique_variant_per_product');
            });
        }

        if (!Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0);
                $table->boolean('is_available')->default(true);
                $table->timestamps();
                
                $table->index(['product_variant_id', 'merchant_id']);
            });
        }

        if (!Schema::hasTable('offers')) {
            Schema::create('offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('type', ['banner', 'popup', 'notification', 'discount']);
                $table->string('image')->nullable();
                $table->decimal('value', 10, 2)->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['merchant_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
