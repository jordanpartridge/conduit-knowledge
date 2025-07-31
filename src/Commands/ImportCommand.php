<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Commands;

use Illuminate\Console\Command;
use Jordanpartridge\ConduitKnowledge\Services\MigrationService;

class ImportCommand extends Command
{
    protected $signature = 'knowledge:import 
                            {file : Path to backup JSON file}
                            {--force : Skip confirmations}';

    protected $description = 'Import knowledge data from a backup file';

    public function __construct(private MigrationService $migration)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $backupFile = $this->argument('file');
        
        $this->info('📦 Importing knowledge from backup...');
        $this->line("File: {$backupFile}");
        $this->newLine();

        // Confirm import
        if (!$this->option('force')) {
            if (!$this->confirm('Proceed with import? This will add entries from the backup file.')) {
                $this->info('Import cancelled.');
                return self::SUCCESS;
            }
        }

        // Perform import
        $this->line('📊 Importing data...');
        $results = $this->migration->importFromBackup($backupFile);

        // Display results
        $this->displayResults($results);

        return $results['status'] === 'success' ? self::SUCCESS : self::FAILURE;
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        
        if ($results['status'] === 'error') {
            $this->error('❌ Import failed:');
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
            return;
        }

        $this->info('📊 Import Results:');
        $this->line("   • {$results['entries_migrated']} entries imported");
        $this->line("   • {$results['tags_migrated']} tags updated");
        $this->line("   • {$results['collections_created']} collections created");

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->warn('⚠️  Some errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
        } else {
            $this->newLine();
            $this->info('✅ Import completed successfully!');
            $this->line('🚀 Your knowledge has been restored.');
        }
    }
}