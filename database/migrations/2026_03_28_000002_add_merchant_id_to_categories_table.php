<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'merchant_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'merchant_id')) {
            Schema::table('categories', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['merchant_id']);
                }
                $table->dropColumn('merchant_id');
            });
        }
    }
};
