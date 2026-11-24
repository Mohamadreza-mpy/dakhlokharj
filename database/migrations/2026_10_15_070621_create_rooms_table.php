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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('currency', 10)->default('TOMAN');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
