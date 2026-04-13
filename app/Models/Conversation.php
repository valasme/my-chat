<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
    ];

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @param Builder<self> $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where(function (Builder $q) use ($userId) {
            $q->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId);
        });
    }

    /** @param Builder<self> $query */
    public function scopeBetweenUsers(Builder $query, int $userA, int $userB): void
    {
        $lower = min($userA, $userB);
        $upper = max($userA, $userB);

        $query->where('user_one_id', $lower)
            ->where('user_two_id', $upper);
    }

    /**
     * Exclude conversations with ignored or trashed users for the given user.
     *
     * @param  Builder<self>  $query
     */
    public function scopeExcludingIgnoredAndTrashed(Builder $query, int $userId): void
    {
        $ignoredUserIds = Ignore::forIgnorer($userId)->active()->pluck('ignored_id');

        $trashedUserIds = Contact::whereIn('id', Trash::forUser($userId)->pluck('contact_id'))
            ->selectRaw(
                'CASE WHEN user_id = ? THEN contact_user_id ELSE user_id END as other_user_id',
                [$userId]
            )
            ->pluck('other_user_id');

        $excludeUserIds = $ignoredUserIds->merge($trashedUserIds)->unique()->values();

        $query->when($excludeUserIds->isNotEmpty(), function (Builder $q) use ($userId, $excludeUserIds) {
            $q->where(function (Builder $inner) use ($userId, $excludeUserIds) {
                $inner->where(function (Builder $q2) use ($userId, $excludeUserIds) {
                    $q2->where('user_one_id', $userId)
                        ->whereNotIn('user_two_id', $excludeUserIds);
                })->orWhere(function (Builder $q2) use ($userId, $excludeUserIds) {
                    $q2->where('user_two_id', $userId)
                        ->whereNotIn('user_one_id', $excludeUserIds);
                });
            });
        });
    }

    public function getOtherUser(int $userId): User
    {
        return $this->user_one_id === $userId
            ? $this->userTwo
            : $this->userOne;
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->user_one_id === $userId || $this->user_two_id === $userId;
    }
}
