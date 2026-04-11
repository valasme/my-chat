<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trash extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_id',
        'expires_at',
        'is_quick_delete',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @param Builder<self> $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /** @param Builder<self> $query */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_quick_delete' => 'boolean',
        ];
    }
}
