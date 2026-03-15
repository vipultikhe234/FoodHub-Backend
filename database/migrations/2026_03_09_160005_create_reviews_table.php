<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $row) {
            $row->id();
            $row->foreignId('user_id')->constrained();
            $row->foreignId('product_id')->constrained();
            $row->integer('rating')->default(5);
            $row->text('comment')->nullable();
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
