<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('sender_name');
            $table->string('sender_phone')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_location')->nullable();

            $table->decimal('send_amount', 18, 2);
            $table->string('send_currency', 10)->default('USD');
            $table->decimal('usdt_amount', 18, 6)->nullable();
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('payout_currency', 10)->default('ZMW');
            $table->decimal('payout_amount', 18, 2)->nullable();

            $table->string('status')->default('initiated')->index();

            $table->string('usdt_proof_path')->nullable();
            $table->timestamp('usdt_proof_uploaded_at')->nullable();
            $table->foreignId('usdt_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('usdt_confirmed_at')->nullable();

            $table->string('payout_reference')->nullable();
            $table->string('payout_proof_path')->nullable();
            $table->timestamp('payout_proof_uploaded_at')->nullable();
            $table->foreignId('payout_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payout_confirmed_at')->nullable();

            $table->text('notes')->nullable();
            $table->text('agent_notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['sender_name', 'recipient_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_transfers');
    }
};
