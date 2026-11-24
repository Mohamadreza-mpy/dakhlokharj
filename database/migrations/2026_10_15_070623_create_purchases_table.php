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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_member_id')->constrained('room_members')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('kind', 20);
            $table->unsignedBigInteger('amount_toman');
            $table->string('unit_label', 30)->nullable();
            $table->unsignedBigInteger('total_quantity_milliunits')->nullable();
            $table->string('share_mode', 15)->nullable();
            $table->dateTime('purchased_at');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('posted');
            $table->dateTime('voided_at')->nullable();
            $table->text('void_note')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->uuid('void_request_id')->nullable();
            $table->char('void_payload_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'request_id']);
            $table->unique(['room_id', 'void_request_id']);
            $table->index(['room_id', 'status', 'purchased_at', 'id']);
            $table->index(['payer_member_id', 'purchased_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
