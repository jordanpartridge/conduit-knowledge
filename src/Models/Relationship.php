<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    protected $table = 'knowledge_relationships';

    protected $fillable = [
        'from_entry_id',
        'to_entry_id',
        'type',
        'strength',
        'metadata',
    ];

    protected $casts = [
        'strength' => 'float',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TYPES = [
        'depends_on' => 'Depends On',
        'relates_to' => 'Relates To',
        'conflicts_with' => 'Conflicts With',
        'extends' => 'Extends',
        'implements' => 'Implements',
        'references' => 'References',
        'similar_to' => 'Similar To',
    ];

    /**
     * From entry relationship
     */
    public function fromEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'from_entry_id');
    }

    /**
     * To entry relationship
     */
    public function toEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'to_entry_id');
    }

    /**
     * Get human-readable type
     */
    public function getTypeDisplayAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Create bidirectional relationship
     */
    public static function createBidirectional(
        int $fromId,
        int $toId,
        string $type,
        float $strength = 1.0,
        array $metadata = []
    ): array {
        $forward = static::create([
            'from_entry_id' => $fromId,
            'to_entry_id' => $toId,
            'type' => $type,
            'strength' => $strength,
            'metadata' => $metadata,
        ]);

        $reverse = static::create([
            'from_entry_id' => $toId,
            'to_entry_id' => $fromId,
            'type' => $type,
            'strength' => $strength,
            'metadata' => $metadata,
        ]);

        return [$forward, $reverse];
    }
}