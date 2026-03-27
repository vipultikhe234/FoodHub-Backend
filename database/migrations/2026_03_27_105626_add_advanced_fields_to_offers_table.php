<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('merchant_id')->constrained('categories')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->after('category_id')->constrained('products')->nullOnDelete();
                $table->string('banner_url')->nullable()->after('image'); // Will eventually replace image
                $table->enum('discount_type', ['percentage', 'flat'])->default('percentage')->after('banner_url');
                $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
                $table->integer('priority')->default(0)->after('discount_value');
                $table->integer('usage_count')->default(0)->after('priority');
                $table->timestamp('start_date')->nullable()->after('usage_count');
                $table->timestamp('end_date')->nullable()->after('start_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn([
                'category_id', 'product_id', 'banner_url', 'discount_type', 
                'discount_value', 'priority', 'usage_count', 'start_date', 'end_date'
            ]);
        });
    }
};
