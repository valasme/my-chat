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
        Schema::create('ignores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ignorer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ignored_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->unique(['ignorer_id', 'ignored_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ignores');
    }
};
