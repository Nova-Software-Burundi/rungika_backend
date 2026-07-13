<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('trading_enabled');
            $table->boolean('is_agent_available')->default(false)->after('last_activity_at');
            $table->timestamp('agent_available_since')->nullable()->after('is_agent_available');
            $table->string('agent_location', 255)->nullable()->after('agent_available_since');
            $table->text('agent_bio')->nullable()->after('agent_location');
            $table->string('agent_photo_path', 255)->nullable()->after('agent_bio');
            $table->timestamp('agent_verified_at')->nullable()->after('agent_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_activity_at',
                'is_agent_available',
                'agent_available_since',
                'agent_location',
                'agent_bio',
                'agent_photo_path',
                'agent_verified_at',
            ]);
        });
    }
};
