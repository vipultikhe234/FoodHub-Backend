<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->unique(['state_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
