<?php

/**
 * User Model
 *
 * Core authentication model for the application. Extends Laravel's
 * Authenticatable base and integrates Fortify two-factor authentication.
 *
 * Contact relationships:
 * - contacts()      → HasMany Contact records owned by this user.
 * - contactUsers()  → BelongsToMany through the contacts pivot table.
 * - hasContact()    → Quick boolean check for duplicate prevention.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contact> $contacts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $contactUsers
 *
 * @see \Database\Factories\UserFactory
 */

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the contact entries owned by this user.
     *
     * Each Contact record links this user to another user they have
     * added. Use with('person') to eager-load the contact's profile.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    /**
     * Get the users this user has added as contacts (through pivot).
     *
     * This is a convenience relationship that skips the Contact model
     * and returns User models directly via the contacts pivot table.
     */
    public function contactUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contacts', 'user_id', 'contact_id')
            ->withTimestamps();
    }

    /**
     * Determine if the given user is already in this user's contacts.
     *
     * Used by ContactController@store to prevent duplicate entries
     * before the unique constraint is hit at the database level.
     */
    public function hasContact(User $user): bool
    {
        return $this->contacts()->where('contact_id', $user->id)->exists();
    }

    /**
     * Get the user's initials (first letter of first two words).
     *
     * Used in avatar components when no profile image is available.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
