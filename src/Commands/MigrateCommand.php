<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Commands;

use Illuminate\Console\Command;
use Jordanpartridge\ConduitKnowledge\Services\MigrationService;

class MigrateCommand extends Command
{
    protected $signature = 'knowledge:migrate-from-core 
                            {--backup= : Backup file path}
                            {--remove-core : Remove core tables after migration}
                            {--force : Skip confirmations}';

    protected $description = 'Migrate knowledge data from Conduit core system';

    public function __construct(private MigrationService $migration)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🚀 Starting knowledge migration from Conduit core...');
        $this->newLine();

        // Create backup if requested
        if ($backupPath = $this->option('backup')) {
            $this->line('📦 Creating backup...');
            if ($this->migration->backupCoreData($backupPath)) {
                $this->info("✅ Backup created: {$backupPath}");
            } else {
                $this->error('❌ Backup failed');
                return self::FAILURE;
            }
            $this->newLine();
        }

        // Confirm migration
        if (!$this->option('force')) {
            if (!$this->confirm('Proceed with migration? This will copy all core knowledge data.')) {
                $this->info('Migration cancelled.');
                return self::SUCCESS;
            }
        }

        // Perform migration
        $this->line('📊 Migrating data...');
        $results = $this->migration->migrateFromCore();

        // Display results
        $this->displayResults($results);

        // Optional core cleanup
        if ($this->option('remove-core') && empty($results['errors'])) {
            if ($this->option('force') || $this->confirm('Remove core knowledge tables?')) {
                $this->line('🧹 Removing core system...');
                $cleanup = $this->migration->removeCoreSystem();
                
                if (empty($cleanup['errors'])) {
                    $this->info('✅ Core system removed successfully');
                } else {
                    $this->error('❌ Core removal failed: ' . implode(', ', $cleanup['errors']));
                }
            }
        }

        return empty($results['errors']) ? self::SUCCESS : self::FAILURE;
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Migration Results:');
        $this->line("   • {$results['entries_migrated']} entries migrated");
        $this->line("   • {$results['tags_migrated']} tags migrated");  
        $this->line("   • {$results['collections_created']} collections created");

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
        } else {
            $this->newLine();
            $this->info('✅ Migration completed successfully!');
            $this->line('🚀 Enhanced knowledge system is now ready to use.');
            $this->newLine();
            $this->line('💡 Try these new commands:');
            $this->line('   • knowledge:search --semantic');
            $this->line('   • knowledge:publish --format=html');
            $this->line('   • knowledge:add "content" --auto-tags');
        }
    }
}