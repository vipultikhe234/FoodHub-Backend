<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $row) {
            $row->id();
            $row->foreignId('order_id')->constrained()->cascadeOnDelete();
            $row->foreignId('product_id')->constrained();
            $row->integer('quantity');
            $row->decimal('price', 10, 2);
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
