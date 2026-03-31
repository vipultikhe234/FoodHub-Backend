<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
