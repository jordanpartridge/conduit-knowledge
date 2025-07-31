<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Concerns;

use Illuminate\Support\Facades\Cache;
use Jordanpartridge\ConduitKnowledge\Models\Entry;

trait ChecksMigration
{
    /**
     * Check if this is first run and show helpful message
     */
    protected function checkFirstRun(): void
    {
        // Skip if already checked in this session
        if (Cache::get('knowledge_first_run_checked', false)) {
            return;
        }

        // Mark as checked for 24 hours
        Cache::put('knowledge_first_run_checked', true, 86400);

        // Check if we have any entries
        try {
            $hasEntries = Entry::exists();
            if ($hasEntries) {
                return; // Already have data
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Show first run message
        $this->line('');
        $this->info('🎉 Welcome to Conduit Knowledge!');
        $this->line('');
        $this->line('This appears to be your first time using the knowledge component.');
        $this->line('');
        $this->warn('📦 If you have data from the old Conduit knowledge system:');
        $this->line('   • For Conduit < v2.13.0: Run `conduit knowledge:migrate-from-core`');
        $this->line('   • For Conduit >= v2.13.0: Legacy tables were removed');
        $this->line('   • If you have a backup: Run `conduit knowledge:import <file>`');
        $this->line('');
        $this->info('💡 Get started with:');
        $this->line('   • knowledge:add "Your first knowledge entry"');
        $this->line('   • knowledge:search <query>');
        $this->line('   • knowledge:publish --format=html');
        $this->line('');
    }
}