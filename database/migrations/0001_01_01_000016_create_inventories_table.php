<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
