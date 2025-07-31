<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitKnowledge\Services;

use Illuminate\Support\Collection;
use Jordanpartridge\ConduitKnowledge\Models\Entry;

class SemanticSearchService
{
    /**
     * Check if semantic search is enabled
     */
    public function isEnabled(): bool
    {
        // In production, this would check for vector database setup
        // For now, fallback to traditional search
        return false;
    }

    /**
     * Generate vector embedding for content
     */
    public function generateEmbedding(string $content): ?array
    {
        // In production, this would call an embedding API (OpenAI, etc.)
        // For now, return null (handled gracefully by models)
        return null;
    }

    /**
     * Semantic search for entries
     */
    public function search(string $query, int $limit = 10): Collection
    {
        // In production, this would perform vector similarity search
        // For now, fallback to traditional text search
        return Entry::search($query)->withDetails()->limit($limit)->get();
    }

    /**
     * Find semantically similar entries
     */
    public function findSimilar(Entry $entry, int $limit = 5): Collection
    {
        // In production, this would use vector similarity
        // For now, find entries with shared tags
        return Entry::relatedTo($entry)->withDetails()->limit($limit)->get();
    }

    /**
     * Suggest tags based on content analysis
     */
    public function suggestTags(string $content): array
    {
        // In production, this would use AI for tag suggestions
        // For now, simple keyword-based suggestions
        $suggestions = [];

        // Simple keyword matching
        $keywords = [
            'bug' => ['bug', 'issue', 'error'],
            'feature' => ['feature', 'enhancement', 'add'],
            'performance' => ['slow', 'fast', 'optimize', 'performance'],
            'security' => ['security', 'auth', 'token', 'password'],
            'database' => ['database', 'sql', 'query', 'table'],
            'api' => ['api', 'endpoint', 'rest', 'graphql'],
        ];

        $lowerContent = strtolower($content);
        
        foreach ($keywords as $tag => $words) {
            foreach ($words as $word) {
                if (str_contains($lowerContent, $word)) {
                    $suggestions[] = $tag;
                    break;
                }
            }
        }

        return array_unique($suggestions);
    }

    /**
     * Calculate content similarity score
     */
    public function calculateSimilarity(string $content1, string $content2): float
    {
        // Simple Jaccard similarity for now
        $words1 = array_unique(str_word_count(strtolower($content1), 1));
        $words2 = array_unique(str_word_count(strtolower($content2), 1));
        
        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));
        
        return $union > 0 ? $intersection / $union : 0;
    }
}