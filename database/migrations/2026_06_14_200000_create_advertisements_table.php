<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['buy', 'sell']);
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiat_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->enum('price_type', ['fixed', 'floating'])->default('fixed');
            $table->decimal('price', 18, 8)->nullable();
            $table->decimal('margin', 5, 2)->nullable();
            $table->decimal('min_order', 18, 8);
            $table->decimal('max_order', 18, 8);
            $table->decimal('available_quantity', 18, 8);
            $table->json('payment_methods');
            $table->text('terms')->nullable();
            $table->enum('status', ['active', 'paused', 'closed'])->default('active');
            $table->text('auto_reply')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
