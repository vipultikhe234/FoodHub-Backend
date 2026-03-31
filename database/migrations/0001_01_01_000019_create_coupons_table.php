<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->enum('type', ['fixed', 'percentage']);
                $table->decimal('value', 10, 2);
                $table->decimal('min_order_amount', 10, 2)->default(0);
                $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
                $table->dateTime('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
