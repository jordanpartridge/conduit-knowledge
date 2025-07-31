<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Commands;

use Illuminate\Console\Command;
use Jordanpartridge\ConduitKnowledge\Services\KnowledgeService;
use Jordanpartridge\ConduitKnowledge\Concerns\ChecksMigration;

class AddCommand extends Command
{
    use ChecksMigration;
    protected $signature = 'knowledge:add 
                            {content : The knowledge content to add}
                            {--tags= : Comma-separated tags}
                            {--priority=medium : Priority level (low, medium, high)}
                            {--status=open : Status (open, in-progress, completed)}
                            {--collection= : Collection to add to}
                            {--auto-tags : Enable AI-powered auto-tagging}';

    protected $description = 'Add enhanced knowledge entry with semantic features';

    public function __construct(private KnowledgeService $knowledge)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->checkFirstRun();
        
        $content = $this->argument('content');
        $tags = $this->option('tags') ? explode(',', $this->option('tags')) : [];
        
        $metadata = [
            'priority' => $this->option('priority'),
            'status' => $this->option('status'),
        ];

        $collectionId = $this->option('collection') 
            ? $this->resolveCollection($this->option('collection'))
            : null;

        $entryId = $this->knowledge->addEntry($content, $tags, $metadata, $collectionId);

        $this->info("✅ Enhanced knowledge captured (ID: {$entryId})");
        
        if ($this->option('auto-tags')) {
            $this->line("🤖 AI auto-tagging enabled");
        }
        
        if ($collectionId) {
            $this->line("📁 Added to collection");
        }

        return self::SUCCESS;
    }

    private function resolveCollection(string $name): ?int
    {
        // This would resolve collection by name or create new one
        return null; // Simplified for now
    }
}