<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $row) {
                $row->id();
                $row->foreignId('order_id')->constrained()->cascadeOnDelete();
                $row->foreignId('product_id')->constrained()->cascadeOnDelete();
                $row->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
                $row->integer('quantity');
                $row->decimal('price', 15, 2);
                $row->decimal('total', 15, 2);
                $row->json('options_snapshot')->nullable();
                $row->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
