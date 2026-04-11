<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    // ── Contact Relationships ──

    public function sentContactRequests(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function receivedContactRequests(): HasMany
    {
        return $this->hasMany(Contact::class, 'contact_user_id');
    }

    /**
     * Get all accepted contacts (both directions).
     *
     * @return Collection<int, Contact>
     */
    public function contacts(): Collection
    {
        return Contact::forUser($this->id)->accepted()->get();
    }

    // ── Block Relationships ──

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_id');
    }

    public function blockedBy(): HasMany
    {
        return $this->hasMany(Block::class, 'blocked_id');
    }

    // ── Ignore Relationships ──

    public function ignores(): HasMany
    {
        return $this->hasMany(Ignore::class, 'ignorer_id');
    }

    public function ignoredBy(): HasMany
    {
        return $this->hasMany(Ignore::class, 'ignored_id');
    }

    // ── Helper Methods ──

    public function isContactOf(User $user): bool
    {
        return Contact::between($this->id, $user->id)->accepted()->exists();
    }

    public function hasPendingContactWith(User $user): bool
    {
        return Contact::between($this->id, $user->id)->pending()->exists();
    }

    public function hasAnyContactWith(User $user): bool
    {
        return Contact::between($this->id, $user->id)->exists();
    }

    public function hasBlockedUser(User $user): bool
    {
        return $this->blocks()->where('blocked_id', $user->id)->exists();
    }

    public function isBlockedByUser(User $user): bool
    {
        return $user->hasBlockedUser($this);
    }

    public function isIgnoringUser(User $user): bool
    {
        return $this->ignores()
            ->where('ignored_id', $user->id)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function isIgnoredByUser(User $user): bool
    {
        return $user->isIgnoringUser($this);
    }
}
