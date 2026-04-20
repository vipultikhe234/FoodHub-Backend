<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // New granular tax columns for deep financial transparency as requested
            if (!Schema::hasColumn('orders', 'items_tax')) {
                $table->decimal('items_tax', 15, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('orders', 'packaging_tax')) {
                $table->decimal('packaging_tax', 15, 2)->default(0)->after('packaging_fee');
            }
            if (!Schema::hasColumn('orders', 'delivery_tax')) {
                $table->decimal('delivery_tax', 15, 2)->default(0)->after('delivery_fee');
            }
            if (!Schema::hasColumn('orders', 'platform_tax')) {
                $table->decimal('platform_tax', 15, 2)->default(0)->after('platform_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['items_tax', 'packaging_tax', 'delivery_tax', 'platform_tax']);
        });
    }
};
