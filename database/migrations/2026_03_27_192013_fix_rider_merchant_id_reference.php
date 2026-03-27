<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key safely if exists
            $table->dropForeign(['merchant_id']);
        });
        
        // Set invalid merchant ids to null to prevent foreign key errors
        DB::statement('UPDATE users SET merchant_id = NULL WHERE merchant_id NOT IN (SELECT id FROM merchants)');
        
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->nullOnDelete();
        });
    }

    public function down(): void
    {
    }
};
