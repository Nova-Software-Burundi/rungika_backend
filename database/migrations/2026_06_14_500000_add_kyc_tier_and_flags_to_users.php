<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('kyc_tier')->default(0)->after('kyc_verified_at');
            $table->boolean('flagged')->default(false)->after('kyc_tier');
            $table->text('flagged_reason')->nullable()->after('flagged');
            $table->boolean('trading_enabled')->default(true)->after('flagged_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kyc_tier', 'flagged', 'flagged_reason', 'trading_enabled']);
        });
    }
};
