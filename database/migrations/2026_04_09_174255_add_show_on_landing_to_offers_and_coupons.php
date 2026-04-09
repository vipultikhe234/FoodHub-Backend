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
            $table->boolean('show_on_landing')->default(false);
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('show_on_landing')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('show_on_landing');
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('show_on_landing');
        });
    }
};
