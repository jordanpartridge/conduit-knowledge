<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $table = 'knowledge_collections';

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'is_private',
        'metadata',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Entries in this collection
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Get entry count for this collection
     */
    public function getEntryCountAttribute(): int
    {
        return $this->entries()->count();
    }

    /**
     * Get recent entries count
     */
    public function getRecentEntriesCountAttribute(): int
    {
        return $this->entries()->recent(7)->count();
    }
}