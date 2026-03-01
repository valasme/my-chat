<?php

/**
 * Contact Model
 *
 * Represents a user-to-user contact relationship (like Discord friend requests).
 * Each record links an owner (user_id) to another user (contact_id).
 * A unique constraint on [user_id, contact_id] prevents duplicate entries.
 * Cascade deletes ensure contacts are cleaned up when either user is removed.
 *
 * @property int $id
 * @property int $user_id The user who added this contact.
 * @property int $contact_id The user who was added as a contact.
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User $owner  The user who owns this contact entry.
 * @property-read User $person The user stored as a contact.
 *
 * @see \App\Http\Controllers\ContactController
 * @see \App\Policies\ContactPolicy
 * @see \Database\Factories\ContactFactory
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * Only contact_id is set during creation — user_id is automatically
     * filled by the HasMany relationship on the User model.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'contact_id',
    ];

    /**
     * Get the user who owns this contact entry.
     *
     * This is the user who initiated the "add contact" action.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who is stored as a contact.
     *
     * This is the person whose profile info (name, email) is displayed.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_id');
    }
}
