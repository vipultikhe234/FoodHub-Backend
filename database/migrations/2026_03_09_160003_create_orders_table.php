<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $row) {
            $row->id();
            $row->foreignId('user_id')->constrained()->cascadeOnDelete();
            $row->decimal('total_price', 10, 2);
            $row->text('address');
            $row->string('payment_method')->default('COD');
            $row->string('status')->default('pending')->index(); // pending, preparing, dispatched, delivered, cancelled
            $row->string('payment_status')->default('pending')->index();
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
