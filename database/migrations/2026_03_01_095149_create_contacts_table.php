<?php

/**
 * Create Contacts Table Migration
 *
 * Builds the contacts table that stores user-to-user contact relationships.
 * Works like a Discord friends list — each row links an owner (user_id)
 * to another user they've "added" (contact_id).
 *
 * Constraints:
 *   - user_id FK    → users.id with CASCADE on delete.
 *   - contact_id FK → users.id with CASCADE on delete.
 *   - UNIQUE(user_id, contact_id) prevents duplicate contact entries.
 *
 * @see \App\Models\Contact
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the contacts pivot table with foreign keys to the users
     * table and a composite unique index to prevent duplicates.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
