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
        Schema::create('consumption_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('consumer_member_id')->constrained('room_members')->restrictOnDelete();
            $table->bigInteger('quantity_milliunits');
            $table->bigInteger('allocated_amount_toman');
            $table->string('entry_type', 15);
            $table->foreignId('reverses_entry_id')->nullable()->unique()->constrained('consumption_entries')->restrictOnDelete();
            $table->dateTime('consumed_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('result_remaining_quantity_milliunits')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'request_id']);
            $table->index(['purchase_id', 'created_at', 'id']);
            $table->index(['consumer_member_id', 'consumed_at']);
            $table->index(['room_id', 'consumed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumption_entries');
    }
};
