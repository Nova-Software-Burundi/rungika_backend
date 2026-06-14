<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
