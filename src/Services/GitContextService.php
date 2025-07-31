<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Services;

use Symfony\Component\Process\Process;

class GitContextService
{
    /**
     * Get current git context
     */
    public function getCurrentContext(): array
    {
        $context = [
            'repo' => null,
            'branch' => null,
            'commit_sha' => null,
            'author' => null,
            'project_type' => null,
        ];

        try {
            // Get repo name
            $remoteUrl = $this->runGitCommand(['git', 'remote', 'get-url', 'origin']);
            if ($remoteUrl && preg_match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?/?$#', $remoteUrl, $matches)) {
                $context['repo'] = $matches[1];
            }

            $context['branch'] = $this->runGitCommand(['git', 'branch', '--show-current']);
            $context['commit_sha'] = substr($this->runGitCommand(['git', 'rev-parse', 'HEAD']) ?: '', 0, 7);
            $context['author'] = $this->runGitCommand(['git', 'config', 'user.name']);
            $context['project_type'] = $this->detectProjectType();

        } catch (\Exception $e) {
            // Git context is optional
        }

        return $context;
    }

    /**
     * Run a git command safely
     */
    private function runGitCommand(array $command): ?string
    {
        try {
            $process = new Process($command);
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Detect project type
     */
    private function detectProjectType(): ?string
    {
        if (file_exists('composer.json')) {
            $composer = json_decode(file_get_contents('composer.json'), true);

            if (isset($composer['require']['laravel-zero/framework'])) {
                return 'laravel-zero';
            }

            if (isset($composer['require']['laravel/framework'])) {
                return 'laravel';
            }

            return 'php';
        }

        if (file_exists('package.json')) {
            return 'node';
        }

        return null;
    }
}