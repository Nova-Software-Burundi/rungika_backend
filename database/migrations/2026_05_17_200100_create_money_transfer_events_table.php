<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_transfer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('money_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['money_transfer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_transfer_events');
    }
};
