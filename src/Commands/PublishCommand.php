<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Commands;

use Illuminate\Console\Command;
use Jordanpartridge\ConduitKnowledge\Services\KnowledgeService;
use Jordanpartridge\ConduitKnowledge\Services\PublishService;

class PublishCommand extends Command
{
    protected $signature = 'knowledge:publish 
                            {--format=html : Output format (html, markdown, json, api)}
                            {--output= : Output directory}
                            {--theme=modern : Theme (modern, minimal, technical)}
                            {--collection= : Specific collection to publish}
                            {--public : Make site publicly searchable}
                            {--api : Generate REST API endpoints}';

    protected $description = 'Publish your knowledge base as a static site, API, or documentation';

    public function __construct(
        private KnowledgeService $knowledge,
        private PublishService $publisher
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');
        $output = $this->option('output') ?: getcwd() . '/knowledge-site';
        $theme = $this->option('theme');
        
        $this->info("🚀 Publishing knowledge base as {$format}...");
        $this->newLine();

        // Generate the site
        $result = $this->publisher->publish([
            'format' => $format,
            'output' => $output,
            'theme' => $theme,
            'collection' => $this->option('collection'),
            'public' => $this->option('public'),
            'api' => $this->option('api'),
        ]);

        $this->displayResults($result);
        
        return self::SUCCESS;
    }

    private function displayResults(array $result): void
    {
        $this->info("✅ Knowledge base published successfully!");
        $this->newLine();
        
        $this->line("📊 <comment>Statistics:</comment>");
        $this->line("   • {$result['entries_count']} entries published");
        $this->line("   • {$result['collections_count']} collections organized");
        $this->line("   • {$result['tags_count']} tags indexed");
        $this->newLine();
        
        $this->line("🌐 <comment>Generated:</comment>");
        foreach ($result['files'] as $file) {
            $this->line("   • {$file}");
        }
        $this->newLine();
        
        if ($result['api_enabled']) {
            $this->line("🔌 <comment>API Endpoints:</comment>");
            $this->line("   • GET /api/search?q=query");
            $this->line("   • GET /api/entries/{id}");
            $this->line("   • GET /api/collections");
            $this->line("   • GET /api/tags");
            $this->newLine();
        }
        
        if ($result['seo_optimized']) {
            $this->line("🎯 <comment>SEO Features:</comment>");
            $this->line("   • Semantic HTML structure");
            $this->line("   • Knowledge graph schema.org markup");
            $this->line("   • Full-text search index");
            $this->line("   • Social sharing meta tags");
            $this->newLine();
        }
        
        $this->line("💡 <comment>Next Steps:</comment>");
        $this->line("   • Deploy to GitHub Pages, Netlify, or Vercel");
        $this->line("   • Add custom domain for professional presence");
        $this->line("   • Share knowledge URLs in documentation");
        
        if ($this->option('public')) {
            $this->newLine();
            $this->line("🚀 <comment>Pro Tip:</comment> Your knowledge is now Google-discoverable!");
            $this->line("   Position yourself as the expert on topics you've documented.");
        }
    }
}