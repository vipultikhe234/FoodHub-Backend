<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('merchant_other_charges')) {
            Schema::create('merchant_other_charges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->decimal('delivery_charge', 10, 2)->default(0);
                $table->decimal('packaging_charge', 10, 2)->default(0);
                $table->decimal('platform_fee', 10, 2)->default(0);
                $table->decimal('delivery_charge_tax', 5, 2)->default(0);
                $table->decimal('packaging_charge_tax', 5, 2)->default(0);
                $table->decimal('platform_fee_tax', 5, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_other_charges');
    }
};
