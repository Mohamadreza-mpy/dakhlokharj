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
        Schema::create('shared_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_member_id')->constrained('room_members')->restrictOnDelete();
            $table->unsignedBigInteger('amount_toman');
            $table->timestamps();

            $table->unique(['purchase_id', 'room_member_id']);
            $table->index(['room_member_id', 'purchase_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_allocations');
    }
};
