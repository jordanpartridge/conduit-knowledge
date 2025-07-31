<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Jordanpartridge\ConduitKnowledge\Commands\InitCommand;
use Jordanpartridge\ConduitKnowledge\Commands\StatusCommand;
use Jordanpartridge\ConduitKnowledge\Commands\ConfigureCommand;
use Jordanpartridge\ConduitKnowledge\Commands\ListCommand;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InitCommand::class,
                StatusCommand::class,
                ConfigureCommand::class,
                ListCommand::class
            ]);
        }
    }
}