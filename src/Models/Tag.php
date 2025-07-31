<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $table = 'knowledge_tags';

    protected $fillable = [
        'name',
        'usage_count',
        'color',
        'description',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Entries with this tag
     */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(
            Entry::class,
            'knowledge_entry_tags',
            'tag_id',
            'entry_id'
        )->withTimestamps();
    }

    /**
     * Find or create tag by name
     */
    public static function findOrCreateByName(string $name): self
    {
        $tag = static::where('name', $name)->first();

        if (!$tag) {
            $tag = static::create([
                'name' => $name,
                'usage_count' => 0,
            ]);
        }

        return $tag;
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Decrement usage count
     */
    public function decrementUsage(): void
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }
    }

    /**
     * Get popular tags
     */
    public static function popular(int $limit = 20)
    {
        return static::orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();
    }
}