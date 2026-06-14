<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('ad_id')->constrained('advertisements')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiat_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            $table->enum('status', [
                'pending',
                'awaiting_payment',
                'payment_sent',
                'released',
                'completed',
                'cancelled',
                'disputed',
                'resolved',
            ])->default('pending');

            $table->decimal('asset_amount', 18, 8);
            $table->decimal('fiat_amount', 18, 8);
            $table->decimal('price', 18, 8);

            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->text('payment_details')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();
            $table->timestamp('seller_confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('cancelled_by', ['buyer', 'seller', 'system', 'admin'])->nullable();

            $table->text('dispute_reason')->nullable();
            $table->timestamp('dispute_opened_at')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->foreignId('dispute_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dispute_resolution')->nullable();

            $table->decimal('fee_buyer', 18, 8)->default(0);
            $table->decimal('fee_seller', 18, 8)->default(0);

            $table->timestamps();
        });

        Schema::create('trade_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->enum('actor_type', ['buyer', 'seller', 'system', 'admin']);
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_events');
        Schema::dropIfExists('trades');
    }
};
