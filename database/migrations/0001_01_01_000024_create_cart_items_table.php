<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
