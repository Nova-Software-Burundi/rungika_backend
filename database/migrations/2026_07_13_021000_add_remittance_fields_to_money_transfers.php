<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_transfers', function (Blueprint $table) {
            // Destinator fields (the person receiving the money)
            $table->string('destinator_name', 255)->nullable()->after('recipient_location');
            $table->string('destinator_phone', 50)->nullable()->after('destinator_name');
            $table->string('destinator_address', 255)->nullable()->after('destinator_phone');
            $table->foreignId('destinator_payment_method_id')->nullable()->after('destinator_address')->constrained('payment_methods')->nullOnDelete();
            $table->string('destinator_account_number', 255)->nullable()->after('destinator_payment_method_id');
            $table->text('destinator_notes')->nullable()->after('destinator_account_number');

            // Requester proof (replaces usdt_proof_path)
            $table->string('requester_proof_path', 255)->nullable()->after('destinator_notes');
            $table->boolean('requester_debt')->default(false)->after('requester_proof_path');
            $table->timestamp('requester_proof_uploaded_at')->nullable()->after('requester_debt');

            // Executor proof (replaces payout_proof_path for executor)
            $table->string('executor_proof_path', 255)->nullable()->after('requester_proof_uploaded_at');
            $table->boolean('executor_debt')->default(false)->after('executor_proof_path');
            $table->timestamp('executor_proof_uploaded_at')->nullable()->after('executor_debt');

            // Status tracking fields
            $table->timestamp('accepted_at')->nullable()->after('executor_proof_uploaded_at');
            $table->timestamp('executed_at')->nullable()->after('accepted_at');
            $table->timestamp('completed_at')->nullable()->after('executed_at');

            // Make old USDT fields nullable (keep for backward compat with existing records)
            $table->decimal('usdt_amount', 18, 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('money_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'destinator_name',
                'destinator_phone',
                'destinator_address',
                'destinator_payment_method_id',
                'destinator_account_number',
                'destinator_notes',
                'requester_proof_path',
                'requester_debt',
                'requester_proof_uploaded_at',
                'executor_proof_path',
                'executor_debt',
                'executor_proof_uploaded_at',
                'accepted_at',
                'executed_at',
                'completed_at',
            ]);
        });
    }
};
