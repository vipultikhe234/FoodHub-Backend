<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('offers')) {
            Schema::create('offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('type', ['banner', 'popup', 'notification', 'discount']);
                $table->string('image')->nullable();
                $table->decimal('value', 10, 2)->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['merchant_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
