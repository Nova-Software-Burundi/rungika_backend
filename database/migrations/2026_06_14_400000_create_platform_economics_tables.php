<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiat_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->enum('buyer_fee_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('buyer_fee_value', 18, 8)->default(0);
            $table->enum('seller_fee_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('seller_fee_value', 18, 8)->default(0);
            $table->decimal('min_fee', 18, 8)->nullable();
            $table->decimal('max_fee', 18, 8)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['asset_id', 'fiat_currency_id']);
        });

        Schema::create('reference_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiat_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('price', 18, 8);
            $table->string('source', 50)->default('manual');
            $table->timestamp('valid_at')->useCurrent();
            $table->timestamps();

            $table->index(['asset_id', 'fiat_currency_id', 'valid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_prices');
        Schema::dropIfExists('platform_fees');
    }
};
