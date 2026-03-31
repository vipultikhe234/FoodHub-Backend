<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'merchant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'merchant_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Ignore drop foreign as SQLite doesn't support dropping foreign keys nicely 
                // in older versions, but if needed we do it. Better to keep it simple.
                $table->dropConstrainedForeignId('merchant_id');
            });
        }
    }
};
