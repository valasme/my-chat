<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'contact_id',
        'title',
        'body',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

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
    public function scopePersonal(Builder $query): void
    {
        $query->whereNull('contact_id');
    }

    /** @param Builder<self> $query */
    public function scopeForContact(Builder $query, int $contactId): void
    {
        $query->where('contact_id', $contactId);
    }

    /** @param Builder<self> $query */
    public function scopeWithTag(Builder $query, string $tag): void
    {
        $query->whereJsonContains('tags', $tag);
    }
}
