<?php

namespace App\Services;

/**
 * Pre-computes objective metrics so the article-score LLM skill can skip
 * mechanical work (string counting, regex matching) and focus on the
 * subjective judgment calls (virality evidence, quality evidence, GEO
 * entity clarity, AI pattern detection). Cuts score phase from ~11 min
 * to ~2-3 min on a 2,000-word article.
 *
 * All methods are pure and deterministic — same input always yields the
 * same output. Safe to re-run.
 */
class MechanicalScoringService
{
    private const TIER_1_WORDS = [
        'delve', 'delve into', 'landscape', 'tapestry', 'realm', 'paradigm',
        'embark', 'beacon', 'testament to', 'robust', 'comprehensive',
        'cutting-edge', 'leverage', 'pivotal', 'underscores',
        'meticulous', 'meticulously', 'seamless', 'seamlessly',
        'game-changer', 'game-changing', 'utilize', 'watershed moment',
        'nestled', 'vibrant', 'thriving', 'showcasing', 'deep dive',
        'dive into', 'unpack', 'unpacking', 'bustling', 'intricate',
        'intricacies', 'ever-evolving', 'daunting', 'holistic',
        'holistically', 'actionable', 'impactful', 'learnings',
        'thought leader', 'thought leadership', 'best practices',
        'at its core', 'synergy', 'synergies', 'interplay', 'in order to',
        'due to the fact that', 'serves as', 'boasts', 'commence',
        'ascertain', 'endeavor', 'symphony', 'unlock', 'unleash',
        'supercharge', 'empower', 'enhance', 'exceed', 'maximize',
    ];

    private const TIER_2_WORDS = [
        'harness', 'navigate', 'navigating', 'foster', 'elevate',
        'streamline', 'bolster', 'spearhead', 'resonate', 'resonates',
        'revolutionize', 'facilitate', 'underpin', 'nuanced', 'crucial',
        'multifaceted', 'ecosystem', 'myriad', 'plethora', 'encompass',
        'catalyze', 'reimagine', 'augment', 'cultivate', 'illuminate',
        'elucidate', 'juxtapose', 'paradigm-shifting', 'transformative',
        'transformation', 'cornerstone', 'paramount', 'poised',
        'burgeoning', 'nascent', 'quintessential', 'overarching',
        'galvanize', 'underpinning', 'underpinnings', 'optimal',
        'optimize', 'stakeholder', 'bandwidth', 'synergize', 'ideate',
        'iterate',
    ];

    private const TIER_3_WORDS = [
        'significant', 'significantly', 'innovative', 'innovation',
        'effective', 'effectively', 'dynamic', 'dynamics', 'scalable',
        'scalability', 'compelling', 'unprecedented', 'exceptional',
        'exceptionally', 'remarkable', 'remarkably', 'sophisticated',
        'instrumental', 'world-class', 'state-of-the-art', 'best-in-class',
    ];

    public function analyze(string $title, string $content, string $keyword, array $options = []): array
    {
        $language = $options['language'] ?? 'en';
        $currentYear = (int) ($options['current_year'] ?? date('Y'));

        $plainContent = $this->htmlToPlainText($content);
        $wordCount = $this->countWords($plainContent);

        return [
            'word_count' => $wordCount,
            'seo' => $this->scoreSEO($title, $content, $plainContent, $keyword, $wordCount),
            'freshness_signals' => $this->countFreshnessSignals($plainContent, $currentYear),
            'faq_pair_count' => $this->countFaqPairs($content),
            'h2_count' => $this->countHeadings($content, 2),
            'h3_count' => $this->countHeadings($content, 3),
            'ai_humanization' => $this->scoreAiHumanization($plainContent, $content, $wordCount, $language),
            'language' => $language,
        ];
    }

    private function scoreSEO(string $title, string $html, string $plainContent, string $keyword, int $wordCount): array
    {
        $titleLength = mb_strlen($title);
        $titleWordCount = str_word_count($title);
        $keywordLower = mb_strtolower(trim($keyword));
        $titleLower = mb_strtolower($title);
        $keywordInTitle = $keywordLower !== '' && mb_strpos($titleLower, $keywordLower) !== false;

        $first100Words = implode(' ', array_slice(preg_split('/\s+/', $plainContent, -1, PREG_SPLIT_NO_EMPTY), 0, 100));
        $keywordInFirst100 = $keywordLower !== '' && mb_stripos($first100Words, $keywordLower) !== false;

        $keywordOccurrences = $keywordLower !== ''
            ? mb_substr_count(mb_strtolower($plainContent), $keywordLower)
            : 0;
        $density = $wordCount > 0 ? round(($keywordOccurrences / $wordCount) * 100, 2) : 0.0;

        $keywordInHeadings = 0;
        if ($keywordLower !== '' && preg_match_all('/<h[2-3][^>]*>(.*?)<\/h[2-3]>/is', $html, $matches)) {
            foreach ($matches[1] as $heading) {
                if (mb_stripos(strip_tags($heading), $keywordLower) !== false) {
                    $keywordInHeadings++;
                }
            }
        }

        return [
            'title_length' => [
                'value' => $titleLength,
                'status' => $this->trafficLight($titleLength, [50, 60], [40, 70]),
            ],
            'keyword_in_title' => [
                'value' => $keywordInTitle,
                'status' => $keywordInTitle ? 'green' : 'red',
            ],
            'title_word_count' => [
                'value' => $titleWordCount,
                'status' => $this->trafficLight($titleWordCount, [6, 10], [5, 12]),
            ],
            'body_keyword_density' => [
                'value' => $density,
                'occurrences' => $keywordOccurrences,
                'status' => $this->densityLight($density),
            ],
            'keyword_in_first_100' => [
                'value' => $keywordInFirst100,
                'status' => $keywordInFirst100 ? 'green' : 'red',
            ],
            'keyword_in_headings' => [
                'value' => $keywordInHeadings,
                'status' => $this->headingKeywordLight($keywordInHeadings),
            ],
        ];
    }

    private function scoreAiHumanization(string $plainContent, string $html, int $wordCount, string $language): array
    {
        // Tier words are English — skip on pure-ID articles to avoid false positives
        if ($language === 'id') {
            return [
                'tier1_violations' => [],
                'tier1_violation_count' => 0,
                'tier2_clusters' => 0,
                'tier2_cluster_paragraphs' => [],
                'tier3_density_issues' => [],
                'note' => 'Tier word lists are English-specific; skipped for Indonesian article.',
            ];
        }

        $tier1Hits = $this->countTierWordHits($plainContent, self::TIER_1_WORDS);
        $tier3Hits = $this->countTierDensity($plainContent, self::TIER_3_WORDS, $wordCount, 3.0);
        $tier2Clusters = $this->countTier2Clusters($html);

        return [
            'tier1_violations' => $tier1Hits,
            'tier1_violation_count' => array_sum(array_column($tier1Hits, 'count')),
            'tier2_clusters' => $tier2Clusters['cluster_count'],
            'tier2_cluster_paragraphs' => $tier2Clusters['cluster_paragraphs'],
            'tier3_density_issues' => $tier3Hits,
        ];
    }

    private function countTierWordHits(string $plainContent, array $wordList): array
    {
        $contentLower = mb_strtolower($plainContent);
        $hits = [];

        foreach ($wordList as $word) {
            $pattern = str_contains($word, ' ') || str_contains($word, '-')
                ? '/(?<!\w)' . preg_quote($word, '/') . '(?!\w)/iu'
                : '/\b' . preg_quote($word, '/') . '\b/iu';
            if (preg_match_all($pattern, $contentLower, $matches)) {
                $hits[] = ['word' => $word, 'count' => count($matches[0])];
            }
        }

        return $hits;
    }

    private function countTierDensity(string $plainContent, array $wordList, int $totalWords, float $thresholdPct): array
    {
        if ($totalWords === 0) return [];
        $contentLower = mb_strtolower($plainContent);
        $issues = [];

        foreach ($wordList as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
            $count = preg_match_all($pattern, $contentLower);
            if ($count === false) $count = 0;
            $pct = ($count / $totalWords) * 100;
            if ($pct > $thresholdPct) {
                $issues[] = [
                    'word' => $word,
                    'count' => $count,
                    'density_pct' => round($pct, 2),
                ];
            }
        }

        return $issues;
    }

    private function countTier2Clusters(string $html): array
    {
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $paragraphs);
        $clusterParagraphs = [];

        foreach ($paragraphs[1] as $idx => $paragraph) {
            $plain = mb_strtolower(strip_tags($paragraph));
            $hits = 0;
            foreach (self::TIER_2_WORDS as $word) {
                $pattern = str_contains($word, '-')
                    ? '/(?<!\w)' . preg_quote($word, '/') . '(?!\w)/iu'
                    : '/\b' . preg_quote($word, '/') . '\b/iu';
                if (preg_match($pattern, $plain)) {
                    $hits++;
                    if ($hits >= 2) break;
                }
            }
            if ($hits >= 2) {
                $clusterParagraphs[] = [
                    'paragraph_index' => $idx,
                    'snippet' => mb_substr(trim($plain), 0, 120),
                ];
            }
        }

        return [
            'cluster_count' => count($clusterParagraphs),
            'cluster_paragraphs' => $clusterParagraphs,
        ];
    }

    private function countFreshnessSignals(string $plainContent, int $currentYear): int
    {
        $years = range($currentYear - 1, $currentYear + 1);
        $pattern = '/\b(' . implode('|', $years) . ')\b/';
        return preg_match_all($pattern, $plainContent) ?: 0;
    }

    private function countFaqPairs(string $html): int
    {
        // Prefer an explicit FAQ section; fall back to counting H3 inside a container that mentions FAQ
        if (preg_match('/<(section|div)[^>]*>.*?<h[2-3][^>]*>[^<]*FAQ[^<]*<\/h[2-3]>(.*?)<\/\1>/is', $html, $match)) {
            $faqBlock = $match[2];
            return preg_match_all('/<h[3-4][^>]*>/i', $faqBlock) ?: 0;
        }

        // Schema.org FAQPage block in content
        if (preg_match_all('/"@type":\s*"Question"/i', $html, $m)) {
            return count($m[0]);
        }

        return 0;
    }

    private function countHeadings(string $html, int $level): int
    {
        return preg_match_all('/<h' . $level . '[^>]*>/i', $html) ?: 0;
    }

    private function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function countWords(string $plainText): int
    {
        if (trim($plainText) === '') return 0;
        $tokens = preg_split('/\s+/', $plainText, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($tokens) ? count($tokens) : 0;
    }

    private function trafficLight(int $value, array $green, array $amber): string
    {
        [$gLo, $gHi] = $green;
        [$aLo, $aHi] = $amber;
        if ($value >= $gLo && $value <= $gHi) return 'green';
        if ($value >= $aLo && $value <= $aHi) return 'amber';
        return 'red';
    }

    private function densityLight(float $density): string
    {
        if ($density >= 0.5 && $density <= 1.5) return 'green';
        if (($density >= 0.3 && $density < 0.5) || ($density > 1.5 && $density <= 2.5)) return 'amber';
        return 'red';
    }

    private function headingKeywordLight(int $count): string
    {
        if ($count >= 1 && $count <= 2) return 'green';
        if ($count === 0) return 'amber';
        return 'red'; // >3 = stuffing
    }
}
