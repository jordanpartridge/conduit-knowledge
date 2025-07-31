<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Commands;

use Illuminate\Console\Command;
use Jordanpartridge\ConduitKnowledge\Services\KnowledgeService;

class SearchCommand extends Command
{
    protected $signature = 'knowledge:search 
                            {query? : Search query}
                            {--tags= : Filter by tags}
                            {--collection= : Filter by collection}
                            {--priority= : Filter by priority}
                            {--status= : Filter by status}
                            {--recent= : Recent entries (days)}
                            {--semantic : Use semantic similarity search}
                            {--limit=10 : Maximum results}';

    protected $description = 'Search knowledge with enhanced semantic capabilities';

    public function __construct(private KnowledgeService $knowledge)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = $this->argument('query') ?? '';
        
        $filters = array_filter([
            'tags' => $this->option('tags'),
            'collection' => $this->option('collection'),
            'priority' => $this->option('priority'),
            'status' => $this->option('status'),
            'recent' => $this->option('recent'),
            'limit' => $this->option('limit'),
            'semantic' => $this->option('semantic'),
        ]);

        $results = $this->knowledge->searchEntries($query, $filters);

        if ($results->isEmpty()) {
            $this->warn('No knowledge entries found.');
            return self::SUCCESS;
        }

        $this->info("🔍 Found {$results->count()} results" . ($this->option('semantic') ? ' (semantic search)' : ''));
        $this->newLine();

        foreach ($results as $entry) {
            $this->displayEntry($entry);
        }

        return self::SUCCESS;
    }

    private function displayEntry($entry): void
    {
        $tags = implode(', ', $entry->tag_names);
        $priority = $entry->priority;
        $status = $entry->status;

        $this->line("📝 <comment>#{$entry->id}</comment> {$entry->content}");
        $this->line("   🏷️  {$tags}");
        $this->line("   📊 Priority: {$priority} | Status: {$status}");
        $this->line("   📅 {$entry->created_at->diffForHumans()}");
        $this->newLine();
    }
}