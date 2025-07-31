<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Jordanpartridge\ConduitKnowledge\Commands\AddCommand;
use Jordanpartridge\ConduitKnowledge\Commands\SearchCommand;
use Jordanpartridge\ConduitKnowledge\Commands\PublishCommand;
use Jordanpartridge\ConduitKnowledge\Commands\MigrateCommand;
use Jordanpartridge\ConduitKnowledge\Services\KnowledgeService;
use Jordanpartridge\ConduitKnowledge\Services\GitContextService;
use Jordanpartridge\ConduitKnowledge\Services\SemanticSearchService;
use Jordanpartridge\ConduitKnowledge\Services\PublishService;
use Jordanpartridge\ConduitKnowledge\Services\MigrationService;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // Register services
        $this->app->singleton(GitContextService::class);
        $this->app->singleton(SemanticSearchService::class);
        $this->app->singleton(PublishService::class);
        $this->app->singleton(KnowledgeService::class);
        $this->app->singleton(MigrationService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AddCommand::class,
                SearchCommand::class,
                PublishCommand::class,
                MigrateCommand::class,
            ]);
        }
    }
}