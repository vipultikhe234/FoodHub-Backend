<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
