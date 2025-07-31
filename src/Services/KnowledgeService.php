<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jordanpartridge\ConduitKnowledge\Models\Entry;
use Jordanpartridge\ConduitKnowledge\Models\Tag;
use Jordanpartridge\ConduitKnowledge\Models\Collection as KnowledgeCollection;
use Jordanpartridge\ConduitKnowledge\Services\GitContextService;
use Jordanpartridge\ConduitKnowledge\Services\SemanticSearchService;

class KnowledgeService
{
    public function __construct(
        private GitContextService $gitContext,
        private SemanticSearchService $semanticSearch
    ) {}

    /**
     * Add a new knowledge entry with enhanced features
     */
    public function addEntry(
        string $content, 
        array $tags = [], 
        array $metadata = [],
        ?int $collectionId = null
    ): int {
        return DB::transaction(function () use ($content, $tags, $metadata, $collectionId) {
            $gitContext = $this->gitContext->getCurrentContext();
            
            $entry = Entry::create([
                'content' => $content,
                'repo' => $gitContext['repo'],
                'branch' => $gitContext['branch'],
                'commit_sha' => $gitContext['commit_sha'],
                'author' => $gitContext['author'],
                'project_type' => $gitContext['project_type'],
                'collection_id' => $collectionId,
                'vector_embedding' => $this->semanticSearch->generateEmbedding($content),
            ]);

            // Add tags with auto-suggestions
            if (!empty($tags)) {
                $this->attachTagsToEntry($entry, $tags);
            }

            // Add auto-suggested tags based on content
            $autoTags = $this->semanticSearch->suggestTags($content);
            if (!empty($autoTags)) {
                $this->attachTagsToEntry($entry, $autoTags, false); // Don't increment usage for auto-tags
            }

            // Add metadata
            foreach ($metadata as $key => $value) {
                $entry->setMetadataValue($key, (string) $value);
            }

            return $entry->id;
        });
    }

    /**
     * Enhanced search with semantic capabilities
     */
    public function searchEntries(string $query = '', array $filters = []): Collection
    {
        $builder = Entry::query()->withDetails();

        // Semantic search if query provided
        if (!empty($query)) {
            if ($this->semanticSearch->isEnabled()) {
                $semanticResults = $this->semanticSearch->search($query, $filters['limit'] ?? 10);
                $builder->whereIn('id', $semanticResults->pluck('id'));
            } else {
                // Fallback to text search
                $builder->search($query);
            }
        }

        // Apply filters
        $this->applyFilters($builder, $filters);

        $limit = $filters['limit'] ?? 10;
        return $builder->limit($limit)->get();
    }

    /**
     * Get entry with enhanced related content
     */
    public function getEntry(int $id): ?Entry
    {
        $entry = Entry::withDetails()->find($id);
        
        if (!$entry) {
            return null;
        }

        // Load related entries through multiple methods
        $entry->setRelation('semanticallySimilar', 
            $this->semanticSearch->findSimilar($entry, 5)
        );
        
        $entry->setRelation('tagRelated', 
            Entry::relatedTo($entry)->withDetails()->limit(3)->get()
        );

        return $entry;
    }

    /**
     * Create or get collection
     */
    public function createCollection(
        string $name,
        ?string $description = null,
        ?string $color = null,
        ?string $icon = null
    ): KnowledgeCollection {
        return KnowledgeCollection::create([
            'name' => $name,
            'description' => $description,
            'color' => $color ?? '#3B82F6',
            'icon' => $icon ?? '📁',
        ]);
    }

    /**
     * Export knowledge base
     */
    public function export(array $filters = []): array
    {
        $entries = $this->searchEntries('', array_merge($filters, ['limit' => 1000]));
        
        return [
            'version' => '2.0',
            'exported_at' => now()->toISOString(),
            'total_entries' => $entries->count(),
            'entries' => $entries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'content' => $entry->content,
                    'tags' => $entry->tag_names,
                    'metadata' => $entry->metadata->pluck('value', 'key'),
                    'git_context' => [
                        'repo' => $entry->repo,
                        'branch' => $entry->branch,
                        'commit_sha' => $entry->commit_sha,
                        'author' => $entry->author,
                        'project_type' => $entry->project_type,
                    ],
                    'created_at' => $entry->created_at->toISOString(),
                    'updated_at' => $entry->updated_at->toISOString(),
                ];
            })->toArray(),
        ];
    }

    /**
     * Import knowledge base
     */
    public function import(array $data): array
    {
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($data, &$results) {
            foreach ($data['entries'] as $entryData) {
                try {
                    $entryId = $this->addEntry(
                        $entryData['content'],
                        $entryData['tags'] ?? [],
                        $entryData['metadata'] ?? []
                    );
                    
                    $results['imported']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Failed to import entry: " . $e->getMessage();
                    $results['skipped']++;
                }
            }
        });

        return $results;
    }

    /**
     * Delete entry and cleanup relationships
     */
    public function deleteEntry(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $entry = Entry::find($id);
            
            if (!$entry) {
                return false;
            }

            // Decrement tag usage counts
            foreach ($entry->tags as $tag) {
                $tag->decrementUsage();
            }

            // Delete relationships
            $entry->fromRelationships()->delete();
            $entry->toRelationships()->delete();

            // Delete the entry (cascades metadata and tag relationships)
            return $entry->delete();
        });
    }

    /**
     * Apply filters to query builder
     */
    private function applyFilters($builder, array $filters): void
    {
        if (!empty($filters['repo'])) {
            $builder->byRepo($filters['repo']);
        }

        if (!empty($filters['collection'])) {
            $builder->byCollection($filters['collection']);
        }

        if (!empty($filters['branch'])) {
            $builder->byBranch($filters['branch']);
        }

        if (!empty($filters['author'])) {
            $builder->byAuthor($filters['author']);
        }

        if (!empty($filters['type'])) {
            $builder->byProjectType($filters['type']);
        }

        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            $builder->withTags($tags);
        }

        if (!empty($filters['todo'])) {
            $builder->todoOnly();
        }

        if (!empty($filters['priority'])) {
            $builder->byPriority($filters['priority']);
        }

        if (!empty($filters['status'])) {
            $builder->byStatus($filters['status']);
        }

        if (!empty($filters['recent'])) {
            $builder->recent($filters['recent']);
        }

        if (!empty($filters['context'])) {
            $gitContext = $this->gitContext->getCurrentContext();
            if ($gitContext['repo']) {
                $builder->orderByRelevance($gitContext['repo']);
            }
        }
    }

    /**
     * Attach tags to entry with usage tracking
     */
    private function attachTagsToEntry(Entry $entry, array $tags, bool $incrementUsage = true): void
    {
        $tagIds = [];

        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            $tag = Tag::findOrCreateByName($tagName);
            $tagIds[] = $tag->id;

            if ($incrementUsage) {
                $tag->incrementUsage();
            }
        }

        // Sync tags (avoid duplicates)
        $entry->tags()->syncWithoutDetaching($tagIds);
    }
}