<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $row) {
                $row->id();
                $row->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $row->foreignId('category_id')->constrained()->cascadeOnDelete();
                $row->string('name');
                $row->text('description')->nullable();
                $row->decimal('price', 10, 2);
                $row->decimal('discount_price', 10, 2)->nullable();
                $row->string('image')->nullable();
                $row->integer('stock')->default(0);
                $row->boolean('is_veg')->default(false);
                $row->integer('spicy_level')->default(0);
                $row->integer('calories')->nullable();
                $row->integer('preparation_time')->nullable();
                $row->boolean('is_popular')->default(false);
                $row->boolean('is_recommended')->default(false);
                $row->boolean('is_new')->default(false);
                $row->decimal('tax_rate', 5, 2)->nullable();
                $row->boolean('has_variants')->default(false);
                $row->boolean('is_active')->default(true);
                $row->boolean('is_available')->default(true);
                $row->softDeletes();
                $row->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
