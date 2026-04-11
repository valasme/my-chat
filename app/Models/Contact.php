<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'contact_user_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /** @param Builder<self> $query */
    public function scopeAccepted(Builder $query): void
    {
        $query->where('status', 'accepted');
    }

    /** @param Builder<self> $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('contact_user_id', $userId);
        });
    }

    /** @param Builder<self> $query */
    public function scopeIncoming(Builder $query, int $userId): void
    {
        $query->where('contact_user_id', $userId);
    }

    /** @param Builder<self> $query */
    public function scopeOutgoing(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /** @param Builder<self> $query */
    public function scopeBetween(Builder $query, int $userA, int $userB): void
    {
        $query->where(function (Builder $q) use ($userA, $userB) {
            $q->where('user_id', $userA)->where('contact_user_id', $userB)
                ->orWhere(function (Builder $q2) use ($userA, $userB) {
                    $q2->where('user_id', $userB)->where('contact_user_id', $userA);
                });
        });
    }

    /**
     * Get the other user in this contact relationship.
     */
    public function getOtherUser(int $userId): User
    {
        return $this->user_id === $userId
            ? $this->contactUser
            : $this->user;
    }

    /**
     * Check if a user is part of this contact.
     */
    public function involvesUser(int $userId): bool
    {
        return $this->user_id === $userId || $this->contact_user_id === $userId;
    }
}
