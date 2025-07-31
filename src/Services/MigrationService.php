<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Services;

use Illuminate\Support\Facades\DB;
use Jordanpartridge\ConduitKnowledge\Models\Entry;
use Jordanpartridge\ConduitKnowledge\Models\Tag;
use Jordanpartridge\ConduitKnowledge\Models\Collection;

class MigrationService
{
    public function __construct(private KnowledgeService $knowledge)
    {}

    /**
     * Migrate data from core Conduit knowledge system
     */
    public function migrateFromCore(): array
    {
        $results = [
            'entries_migrated' => 0,
            'tags_migrated' => 0,
            'collections_created' => 0,
            'errors' => [],
        ];

        try {
            DB::transaction(function () use (&$results) {
                // Check if core tables exist
                if (!$this->coreTablesExist()) {
                    throw new \Exception('Core knowledge tables not found. Nothing to migrate.');
                }

                // Create default collection for migrated data
                $defaultCollection = Collection::create([
                    'name' => 'Migrated from Core',
                    'description' => 'Knowledge entries migrated from Conduit core system',
                    'color' => '#64748B',
                    'icon' => '📦',
                ]);
                $results['collections_created']++;

                // Migrate entries
                $coreEntries = DB::table('knowledge_entries')->get();
                
                foreach ($coreEntries as $coreEntry) {
                    try {
                        $this->migrateEntry($coreEntry, $defaultCollection->id);
                        $results['entries_migrated']++;
                    } catch (\Exception $e) {
                        $results['errors'][] = "Entry {$coreEntry->id}: " . $e->getMessage();
                    }
                }

                // Migrate tags usage counts
                $coreTags = DB::table('knowledge_tags')->get();
                foreach ($coreTags as $coreTag) {
                    $newTag = Tag::where('name', $coreTag->name)->first();
                    if ($newTag) {
                        $newTag->update(['usage_count' => $coreTag->usage_count]);
                        $results['tags_migrated']++;
                    }
                }
            });

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Check if core knowledge tables exist
     */
    private function coreTablesExist(): bool
    {
        return DB::getSchemaBuilder()->hasTable('knowledge_entries') &&
               DB::getSchemaBuilder()->hasTable('knowledge_tags') &&
               DB::getSchemaBuilder()->hasTable('knowledge_entry_tags');
    }

    /**
     * Migrate individual entry from core system
     */
    private function migrateEntry(object $coreEntry, int $collectionId): void
    {
        // Create enhanced entry
        $entry = Entry::create([
            'content' => $coreEntry->content,
            'repo' => $coreEntry->repo,
            'branch' => $coreEntry->branch,
            'commit_sha' => $coreEntry->commit_sha,
            'author' => $coreEntry->author,
            'project_type' => $coreEntry->project_type,
            'collection_id' => $collectionId,
            'created_at' => $coreEntry->created_at,
            'updated_at' => $coreEntry->updated_at,
        ]);

        // Migrate tags
        $coreTags = DB::table('knowledge_entry_tags as ket')
            ->join('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
            ->where('ket.entry_id', $coreEntry->id)
            ->pluck('kt.name')
            ->toArray();

        if (!empty($coreTags)) {
            $this->attachTagsToEntry($entry, $coreTags);
        }

        // Migrate metadata
        $coreMetadata = DB::table('knowledge_metadata')
            ->where('entry_id', $coreEntry->id)
            ->get();

        foreach ($coreMetadata as $meta) {
            $entry->setMetadataValue($meta->key, $meta->value, $meta->type ?? 'string');
        }
    }

    /**
     * Attach tags to migrated entry
     */
    private function attachTagsToEntry(Entry $entry, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = Tag::findOrCreateByName($tagName);
            $tagIds[] = $tag->id;
        }

        $entry->tags()->sync($tagIds);
    }

    /**
     * Backup core data before migration
     */
    public function backupCoreData(string $backupPath): bool
    {
        try {
            $data = [
                'backup_created_at' => now()->toISOString(),
                'entries' => DB::table('knowledge_entries')->get()->toArray(),
                'tags' => DB::table('knowledge_tags')->get()->toArray(),
                'entry_tags' => DB::table('knowledge_entry_tags')->get()->toArray(),
                'metadata' => DB::table('knowledge_metadata')->get()->toArray(),
            ];

            file_put_contents($backupPath, json_encode($data, JSON_PRETTY_PRINT));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove core knowledge tables (optional cleanup)
     */
    public function removeCoreSystem(): array
    {
        $results = [
            'tables_removed' => [],
            'errors' => [],
        ];

        try {
            DB::transaction(function () use (&$results) {
                $tables = [
                    'knowledge_relationships',
                    'knowledge_metadata', 
                    'knowledge_entry_tags',
                    'knowledge_entries',
                    'knowledge_tags',
                ];

                foreach ($tables as $table) {
                    if (DB::getSchemaBuilder()->hasTable($table)) {
                        DB::getSchemaBuilder()->drop($table);
                        $results['tables_removed'][] = $table;
                    }
                }
            });
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }
}