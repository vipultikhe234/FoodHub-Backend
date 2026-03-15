<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $row) {
            $row->id();
            $row->foreignId('category_id')->constrained()->cascadeOnDelete();
            $row->string('name');
            $row->text('description')->nullable();
            $row->decimal('price', 10, 2);
            $row->decimal('discount_price', 10, 2)->nullable();
            $row->string('image')->nullable();
            $row->integer('stock')->default(0);
            $row->boolean('is_available')->default(true);
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
