<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ignore extends Model
{
    use HasFactory;

    protected $fillable = [
        'ignorer_id',
        'ignored_id',
        'expires_at',
    ];

    public function ignorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignorer_id');
    }

    public function ignored(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /** @param Builder<self> $query */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    /** @param Builder<self> $query */
    public function scopeForIgnorer(Builder $query, int $userId): void
    {
        $query->where('ignorer_id', $userId);
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
