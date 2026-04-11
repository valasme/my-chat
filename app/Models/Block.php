<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'blocker_id',
        'blocked_id',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /** @param Builder<self> $query */
    public function scopeForBlocker(Builder $query, int $userId): void
    {
        $query->where('blocker_id', $userId);
    }

    /** @param Builder<self> $query */
    public function scopeBetween(Builder $query, int $userA, int $userB): void
    {
        $query->where(function (Builder $q) use ($userA, $userB) {
            $q->where('blocker_id', $userA)->where('blocked_id', $userB)
                ->orWhere(function (Builder $q2) use ($userA, $userB) {
                    $q2->where('blocker_id', $userB)->where('blocked_id', $userA);
                });
        });
    }
}
