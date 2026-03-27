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
        Schema::table('countries', function (Blueprint $table) {
            if (!Schema::hasColumn('countries', 'is_active')) {
                $table->boolean('is_active')->after('symbol')->default(true);
            }
        });

        Schema::table('states', function (Blueprint $table) {
            if (!Schema::hasColumn('states', 'is_active')) {
                $table->boolean('is_active')->after('name')->default(true);
            }
        });

        Schema::table('cities', function (Blueprint $table) {
            if (!Schema::hasColumn('cities', 'is_active')) {
                $table->boolean('is_active')->after('name')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
