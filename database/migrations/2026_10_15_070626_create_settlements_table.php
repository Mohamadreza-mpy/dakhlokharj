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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_member_id')->constrained('room_members')->restrictOnDelete();
            $table->foreignId('to_member_id')->constrained('room_members')->restrictOnDelete();
            $table->bigInteger('amount_toman');
            $table->string('entry_type', 15);
            $table->foreignId('reverses_settlement_id')->nullable()->unique()->constrained('settlements')->restrictOnDelete();
            $table->dateTime('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->unique(['room_id', 'request_id']);
            $table->index(['room_id', 'paid_at', 'id']);
            $table->index(['from_member_id', 'to_member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
