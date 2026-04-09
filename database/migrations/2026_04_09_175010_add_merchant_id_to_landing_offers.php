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
        Schema::table('landing_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_id')->nullable()->after('source_id');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('landing_offers', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropColumn('merchant_id');
        });
    }
};
