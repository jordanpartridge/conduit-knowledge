<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Services;

use Illuminate\Support\Facades\File;
use Jordanpartridge\ConduitKnowledge\Models\Entry;
use Jordanpartridge\ConduitKnowledge\Models\Collection;
use Jordanpartridge\ConduitKnowledge\Models\Tag;

class PublishService
{
    /**
     * Publish knowledge base in various formats
     */
    public function publish(array $options): array
    {
        $output = $options['output'];
        $format = $options['format'];
        $theme = $options['theme'];
        
        // Create output directory
        File::makeDirectory($output, 0755, true, true);
        
        $result = [
            'entries_count' => 0,
            'collections_count' => 0,
            'tags_count' => 0,
            'files' => [],
            'api_enabled' => $options['api'] ?? false,
            'seo_optimized' => $format === 'html',
        ];

        switch ($format) {
            case 'html':
                return $this->publishHtml($output, $theme, $options, $result);
            case 'markdown':
                return $this->publishMarkdown($output, $options, $result);
            case 'json':
                return $this->publishJson($output, $options, $result);
            case 'api':
                return $this->publishApi($output, $options, $result);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Publish as HTML static site
     */
    private function publishHtml(string $output, string $theme, array $options, array $result): array
    {
        // Get data
        $entries = Entry::withDetails()->get();
        $collections = Collection::with('entries')->get();
        $tags = Tag::popular(50);

        // Generate HTML files
        $this->generateIndexHtml($output, $entries, $collections, $tags, $theme);
        $this->generateSearchHtml($output, $theme);
        $this->generateEntriesHtml($output, $entries, $theme);
        $this->generateCollectionsHtml($output, $collections, $theme);
        $this->generateTagsHtml($output, $tags, $theme);
        $this->generateAssetsHtml($output, $theme);
        
        if ($options['api'] ?? false) {
            $this->generateApiEndpoints($output, $entries, $collections, $tags);
            $result['api_enabled'] = true;
        }

        $result['entries_count'] = $entries->count();
        $result['collections_count'] = $collections->count();
        $result['tags_count'] = $tags->count();
        $result['files'] = [
            "{$output}/index.html",
            "{$output}/search.html",
            "{$output}/entries/",
            "{$output}/collections/",
            "{$output}/tags/",
            "{$output}/assets/",
        ];

        return $result;
    }

    /**
     * Generate main index.html
     */
    private function generateIndexHtml(string $output, $entries, $collections, $tags, string $theme): void
    {
        $recentEntries = $entries->sortByDesc('created_at')->take(10);
        $topTags = $tags->take(20);

        $html = $this->renderTemplate('index', [
            'title' => 'Knowledge Base',
            'recent_entries' => $recentEntries,
            'collections' => $collections,
            'top_tags' => $topTags,
            'total_entries' => $entries->count(),
            'theme' => $theme,
        ]);

        File::put("{$output}/index.html", $html);
    }

    /**
     * Generate search interface
     */
    private function generateSearchHtml(string $output, string $theme): void
    {
        $html = $this->renderTemplate('search', [
            'title' => 'Search Knowledge Base',
            'theme' => $theme,
        ]);

        File::put("{$output}/search.html", $html);
    }

    /**
     * Generate individual entry pages
     */
    private function generateEntriesHtml(string $output, $entries, string $theme): void
    {
        File::makeDirectory("{$output}/entries", 0755, true, true);

        foreach ($entries as $entry) {
            $html = $this->renderTemplate('entry', [
                'entry' => $entry,
                'related' => $entry->tagRelated ?? collect(),
                'theme' => $theme,
            ]);

            File::put("{$output}/entries/{$entry->id}.html", $html);
        }
    }

    /**
     * Generate API endpoints as JSON files
     */
    private function generateApiEndpoints(string $output, $entries, $collections, $tags): void
    {
        File::makeDirectory("{$output}/api", 0755, true, true);

        // Search index
        $searchIndex = $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'content' => $entry->content,
                'tags' => $entry->tag_names,
                'url' => "/entries/{$entry->id}.html",
                'created_at' => $entry->created_at->toISOString(),
            ];
        });

        File::put("{$output}/api/search.json", json_encode($searchIndex, JSON_PRETTY_PRINT));

        // Individual entries
        File::makeDirectory("{$output}/api/entries", 0755, true, true);
        foreach ($entries as $entry) {
            $entryData = [
                'id' => $entry->id,
                'content' => $entry->content,
                'tags' => $entry->tag_names,
                'metadata' => $entry->metadata->pluck('value', 'key'),
                'created_at' => $entry->created_at->toISOString(),
                'updated_at' => $entry->updated_at->toISOString(),
            ];
            
            File::put("{$output}/api/entries/{$entry->id}.json", json_encode($entryData, JSON_PRETTY_PRINT));
        }

        // Collections endpoint
        File::put("{$output}/api/collections.json", json_encode($collections->toArray(), JSON_PRETTY_PRINT));

        // Tags endpoint
        File::put("{$output}/api/tags.json", json_encode($tags->toArray(), JSON_PRETTY_PRINT));
    }

    /**
     * Render HTML template
     */
    private function renderTemplate(string $template, array $data): string
    {
        $theme = $data['theme'] ?? 'modern';
        
        // This would use a proper templating engine in production
        // For now, generating basic HTML structure
        
        return match ($template) {
            'index' => $this->renderIndexTemplate($data),
            'search' => $this->renderSearchTemplate($data),
            'entry' => $this->renderEntryTemplate($data),
            default => '<html><body><h1>Template not found</h1></body></html>',
        };
    }

    /**
     * Render index template
     */
    private function renderIndexTemplate(array $data): string
    {
        $recentEntries = $data['recent_entries']->map(function ($entry) {
            $tags = implode(', ', $entry->tag_names);
            return "<li><a href='/entries/{$entry->id}.html'>" . e($entry->content) . "</a> <small>({$tags})</small></li>";
        })->implode('');

        $collections = $data['collections']->map(function ($collection) {
            return "<li><a href='/collections/{$collection->id}.html'>{$collection->name}</a> ({$collection->entry_count} entries)</li>";
        })->implode('');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$data['title']}</title>
    <meta name="description" content="Personal knowledge base with {$data['total_entries']} entries">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="{$data['theme']}">
    <header>
        <h1>💡 {$data['title']}</h1>
        <nav>
            <a href="/search.html">🔍 Search</a>
            <a href="/collections/">📁 Collections</a>
            <a href="/tags/">🏷️ Tags</a>
        </nav>
    </header>
    
    <main>
        <section class="stats">
            <div class="stat">
                <h3>{$data['total_entries']}</h3>
                <p>Knowledge Entries</p>
            </div>
            <div class="stat">
                <h3>{$data['collections']->count()}</h3>
                <p>Collections</p>
            </div>
        </section>

        <section class="recent">
            <h2>📝 Recent Entries</h2>
            <ul>{$recentEntries}</ul>
        </section>

        <section class="collections">
            <h2>📁 Collections</h2>
            <ul>{$collections}</ul>
        </section>
    </main>

    <script src="/assets/search.js"></script>
</body>
</html>
HTML;
    }

    /**
     * Render search template  
     */
    private function renderSearchTemplate(array $data): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - {$data['title']}</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="{$data['theme']}">
    <header>
        <h1>🔍 Search Knowledge</h1>
        <nav>
            <a href="/index.html">🏠 Home</a>
        </nav>
    </header>
    
    <main>
        <div class="search-container">
            <input type="text" id="search" placeholder="Search your knowledge..." autofocus>
            <div id="results"></div>
        </div>
    </main>

    <script>
        // Client-side search implementation
        let searchIndex = [];
        
        fetch('/api/search.json')
            .then(r => r.json())  
            .then(data => searchIndex = data);
            
        document.getElementById('search').addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const results = searchIndex.filter(entry => 
                entry.content.toLowerCase().includes(query) ||
                entry.tags.some(tag => tag.toLowerCase().includes(query))
            );
            
            document.getElementById('results').innerHTML = results
                .slice(0, 10)
                .map(entry => `<div class="result">
                    <a href="\${entry.url}">\${entry.content}</a>
                    <div class="tags">\${entry.tags.join(', ')}</div>
                </div>`).join('');
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Render entry template
     */
    private function renderEntryTemplate(array $data): string
    {
        $entry = $data['entry'];
        $tags = implode(', ', $entry->tag_names);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entry #{$entry->id}</title>
    <meta name="description" content="{$entry->content}">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="{$data['theme']}">
    <header>
        <nav>
            <a href="/index.html">🏠 Home</a>
            <a href="/search.html">🔍 Search</a>
        </nav>
    </header>
    
    <main>
        <article class="entry">
            <div class="content">
                <p>{$entry->content}</p>
            </div>
            
            <div class="meta">
                <div class="tags">🏷️ {$tags}</div>
                <div class="date">📅 {$entry->created_at->format('M j, Y')}</div>
                {$entry->repo ? "<div class='repo'>📂 {$entry->repo}</div>" : ""}
            </div>
        </article>
    </main>
</body>
</html>
HTML;
    }

    /**
     * Generate CSS and JS assets
     */
    private function generateAssetsHtml(string $output, string $theme): void
    {
        File::makeDirectory("{$output}/assets", 0755, true, true);

        $css = $this->getThemeCSS($theme);
        File::put("{$output}/assets/style.css", $css);

        $js = $this->getSearchJS();
        File::put("{$output}/assets/search.js", $js);
    }

    /**
     * Get theme CSS
     */
    private function getThemeCSS(string $theme): string
    {
        return <<<CSS
/* Knowledge Base - {$theme} theme */
:root {
    --primary: #3B82F6;
    --secondary: #64748B;
    --background: #FFFFFF;
    --surface: #F8FAFC;
    --text: #1E293B;
    --border: #E2E8F0;
}

* { box-sizing: border-box; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: var(--text);
    background: var(--background);
    margin: 0;
    padding: 20px;
}

header {
    background: var(--surface);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border: 1px solid var(--border);
}

header h1 {
    margin: 0 0 10px 0;
    color: var(--primary);
}

nav a {
    margin-right: 20px;
    text-decoration: none;
    color: var(--secondary);
    font-weight: 500;
}

nav a:hover {
    color: var(--primary);
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat {
    background: var(--surface);
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid var(--border);
}

.stat h3 {
    font-size: 2em;
    margin: 0;
    color: var(--primary);
}

.stat p {
    margin: 5px 0 0 0;
    color: var(--secondary);
}

section {
    background: var(--surface);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.search-container input {
    width: 100%;
    padding: 15px;
    font-size: 1.1em;
    border: 2px solid var(--border);
    border-radius: 8px;
    margin-bottom: 20px;
}

.search-container input:focus {
    outline: none;
    border-color: var(--primary);
}

.result {
    padding: 15px;
    border-bottom: 1px solid var(--border);
}

.result:last-child {
    border-bottom: none;
}

.result a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.tags {
    color: var(--secondary);
    font-size: 0.9em;
    margin-top: 5px;
}

.entry .content {
    font-size: 1.1em;
    line-height: 1.7;
    margin-bottom: 20px;
}

.entry .meta {
    padding-top: 20px;
    border-top: 1px solid var(--border);
    color: var(--secondary);
    font-size: 0.9em;
}

.entry .meta > div {
    margin-bottom: 5px;
}
CSS;
    }

    /**
     * Get search JavaScript
     */
    private function getSearchJS(): string
    {
        return <<<JS
// Knowledge Base Search Enhancement
console.log('🧠 Knowledge Base loaded');

// Add keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.metaKey && e.key === 'k') {
        e.preventDefault();
        window.location.href = '/search.html';
    }
});

// Add search stats
if (document.getElementById('search')) {
    const search = document.getElementById('search');
    const results = document.getElementById('results');
    
    search.addEventListener('input', () => {
        setTimeout(() => {
            const count = results.children.length;
            if (count > 0) {
                results.insertAdjacentHTML('afterbegin', 
                    `<div class="search-stats">Found \${count} results</div>`);
            }
        }, 100);
    });
}
JS;
    }

    /**
     * Publish as Markdown documentation
     */
    private function publishMarkdown(string $output, array $options, array $result): array
    {
        $entries = Entry::withDetails()->get();
        
        // Group by collection or repo
        $grouped = $entries->groupBy('collection.name');
        
        foreach ($grouped as $group => $groupEntries) {
            $filename = $group ? slug($group) : 'uncategorized';
            $content = $this->generateMarkdownContent($groupEntries, $group);
            File::put("{$output}/{$filename}.md", $content);
            $result['files'][] = "{$output}/{$filename}.md";
        }

        $result['entries_count'] = $entries->count();
        return $result;
    }

    /**
     * Generate markdown content
     */
    private function generateMarkdownContent($entries, string $title): string
    {
        $content = "# " . ($title ?: 'Knowledge Base') . "\n\n";
        
        foreach ($entries as $entry) {
            $tags = implode(', ', $entry->tag_names);
            $content .= "## Entry #{$entry->id}\n\n";
            $content .= $entry->content . "\n\n";
            $content .= "**Tags:** {$tags}\n";
            $content .= "**Created:** {$entry->created_at->format('Y-m-d')}\n\n";
            $content .= "---\n\n";
        }
        
        return $content;
    }

    /**
     * Publish as JSON API
     */
    private function publishJson(string $output, array $options, array $result): array
    {
        $entries = Entry::withDetails()->get();
        
        $data = [
            'knowledge_base' => [
                'version' => '2.0',
                'exported_at' => now()->toISOString(),
                'entries' => $entries->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'content' => $entry->content,
                        'tags' => $entry->tag_names,
                        'metadata' => $entry->metadata->pluck('value', 'key'),
                        'created_at' => $entry->created_at->toISOString(),
                    ];
                }),
            ]
        ];
        
        File::put("{$output}/knowledge-base.json", json_encode($data, JSON_PRETTY_PRINT));
        
        $result['entries_count'] = $entries->count();
        $result['files'] = ["{$output}/knowledge-base.json"];
        
        return $result;
    }

    /**
     * Publish as REST API
     */
    private function publishApi(string $output, array $options, array $result): array
    {
        // Generate OpenAPI spec and endpoint documentation
        $this->generateApiEndpoints($output, Entry::withDetails()->get(), Collection::all(), Tag::all());
        
        $spec = $this->generateOpenApiSpec();
        File::put("{$output}/openapi.yaml", $spec);
        
        $result['api_enabled'] = true;
        $result['files'] = [
            "{$output}/api/",
            "{$output}/openapi.yaml"
        ];
        
        return $result;
    }

    /**
     * Generate OpenAPI specification
     */
    private function generateOpenApiSpec(): string
    {
        return <<<YAML
openapi: 3.0.0
info:
  title: Knowledge Base API
  version: 2.0.0
  description: RESTful API for personal knowledge base
paths:
  /api/search.json:
    get:
      summary: Search knowledge entries
      parameters:
        - name: q
          in: query
          schema:
            type: string
      responses:
        200:
          description: Search results
  /api/entries/{id}.json:
    get:
      summary: Get specific entry
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        200:
          description: Entry details
YAML;
    }

    /**
     * Generate collections HTML pages
     */
    private function generateCollectionsHtml(string $output, $collections, string $theme): void
    {
        // Create collections directory
        File::makeDirectory($output . '/collections', 0755, true, true);
        
        // Generate collections index
        $collectionsHtml = $this->renderCollectionsIndexTemplate([
            'collections' => $collections,
            'theme' => $theme,
        ]);
        
        File::put($output . '/collections/index.html', $collectionsHtml);
        
        // Generate individual collection pages
        foreach ($collections as $collection) {
            $collectionHtml = $this->renderCollectionTemplate([
                'collection' => $collection,
                'theme' => $theme,
            ]);
            
            $filename = slug($collection->name) . '.html';
            File::put($output . '/collections/' . $filename, $collectionHtml);
        }
    }

    /**
     * Generate tags HTML pages
     */
    private function generateTagsHtml(string $output, $tags, string $theme): void
    {
        // Create tags directory
        File::makeDirectory($output . '/tags', 0755, true, true);
        
        // Generate tags index
        $tagsHtml = $this->renderTagsIndexTemplate([
            'tags' => $tags,
            'theme' => $theme,
        ]);
        
        File::put($output . '/tags/index.html', $tagsHtml);
        
        // Generate individual tag pages
        foreach ($tags as $tag) {
            $tagHtml = $this->renderTagTemplate([
                'tag' => $tag,
                'theme' => $theme,
            ]);
            
            $filename = slug($tag->name) . '.html';
            File::put($output . '/tags/' . $filename, $tagHtml);
        }
    }

    /**
     * Render collections index template
     */
    private function renderCollectionsIndexTemplate(array $data): string
    {
        $collections = $data['collections'];
        $collectionsHtml = '';
        
        foreach ($collections as $collection) {
            $entriesCount = $collection->entries()->count();
            $collectionsHtml .= <<<HTML
                <div class="collection-card">
                    <h3><a href="/collections/{slug($collection->name)}.html">{$collection->icon} {e($collection->name)}</a></h3>
                    <p>{e($collection->description)}</p>
                    <div class="meta">{$entriesCount} entries</div>
                </div>
HTML;
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections - Knowledge Base</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="{$data['theme']}">
    <header>
        <nav>
            <a href="/index.html">🏠 Home</a>
            <a href="/search.html">🔍 Search</a>
            <a href="/tags/index.html">🏷️ Tags</a>
        </nav>
    </header>
    
    <main>
        <h1>📁 Collections</h1>
        <div class="collections-grid">
            {$collectionsHtml}
        </div>
    </main>
</body>
</html>
HTML;
    }

    /**
     * Render individual collection template
     */
    private function renderCollectionTemplate(array $data): string
    {
        $collection = $data['collection'];
        $entriesHtml = '';
        
        foreach ($collection->entries as $entry) {
            $tags = implode(', ', $entry->tag_names ?? []);
            $entriesHtml .= <<<HTML
                <div class="entry-card">
                    <div class="content">
                        <p>{e($entry->content)}</p>
                    </div>
                    <div class="meta">
                        <div class="tags">🏷️ {$tags}</div>
                        <div class="date">📅 {$entry->created_at->format('M j, Y')}</div>
                    </div>
                </div>
HTML;
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{e($collection->name)} - Collections</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="{$data['theme']}">
    <header>
        <nav>
            <a href="/index.html">🏠 Home</a>
            <a href="/collections/index.html">📁 Collections</a>
            <a href="/search.html">🔍 Search</a>
        </nav>
    </header>
    
    <main>
        <h1>{$collection->icon} {e($collection->name)}</h1>
        <p>{e($collection->description)}</p>
        
        <div class="entries">
            {$entriesHtml}
        </div>
    </main>
</body>
</html>
HTML;
    }

    /**
     * Render tags index template
     */
    private function renderTagsIndexTemplate(array $data): string
    {
        $tags = $data['tags'];
        $tagsHtml = '';
        
        foreach ($tags as $tag) {
            $tagsHtml .= <<<HTML
                <div class="tag-card">
                    <h3><a href="/tags/{slug($tag->name)}.html">🏷️ {e($tag->name)}</a></h3>
                    <div class="meta">{$tag->usage_count} entries</div>
                </div>
HTML;
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tags - Knowledge Base</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="{$data['theme']}">
    <header>
        <nav>
            <a href="/index.html">🏠 Home</a>
            <a href="/search.html">🔍 Search</a>
            <a href="/collections/index.html">📁 Collections</a>
        </nav>
    </header>
    
    <main>
        <h1>🏷️ Tags</h1>
        <div class="tags-grid">
            {$tagsHtml}
        </div>
    </main>
</body>
</html>
HTML;
    }

    /**
     * Render individual tag template
     */
    private function renderTagTemplate(array $data): string
    {
        $tag = $data['tag'];
        $entriesHtml = '';
        
        foreach ($tag->entries as $entry) {
            $otherTags = implode(', ', array_filter($entry->tag_names ?? [], fn($t) => $t !== $tag->name));
            $entriesHtml .= <<<HTML
                <div class="entry-card">
                    <div class="content">
                        <p>{e($entry->content)}</p>
                    </div>
                    <div class="meta">
                        <div class="tags">🏷️ {$otherTags}</div>
                        <div class="date">📅 {$entry->created_at->format('M j, Y')}</div>
                    </div>
                </div>
HTML;
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tag: {e($tag->name)} - Knowledge Base</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="{$data['theme']}">
    <header>
        <nav>
            <a href="/index.html">🏠 Home</a>
            <a href="/tags/index.html">🏷️ Tags</a>
            <a href="/search.html">🔍 Search</a>
        </nav>
    </header>
    
    <main>
        <h1>🏷️ {e($tag->name)}</h1>
        <p>{$tag->usage_count} entries</p>
        
        <div class="entries">
            {$entriesHtml}
        </div>
    </main>
</body>
</html>
HTML;
    }
}

function slug(string $text): string
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}