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
        Schema::table('money_transfers', function (Blueprint $table) {
            $table->foreignId('sender_user_id')->nullable()->after('initiated_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('money_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_user_id');
        });
    }
};
