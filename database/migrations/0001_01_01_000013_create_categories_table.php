<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $row) {
                $row->id();
                $row->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
                $row->string('name');
                $row->string('image')->nullable();
                $row->boolean('status')->default(true);
                $row->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
