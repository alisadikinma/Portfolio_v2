<?php

namespace App\Services;

use App\Models\ContentIdea;

/**
 * Captures a MechanicalScoringService snapshot onto a ContentIdea row at
 * write-done time. Used by the Phase C skip path in
 * /automation/content-ideas/{id}/continue-pipeline when
 * ARTICLE_GEN_USE_SCORE_PHASE=false (default).
 *
 * Thin wrapper — all scoring logic lives in MechanicalScoringService. This
 * class owns the idea-field-extraction + persistence + captured_at stamp.
 */
class MechanicalSnapshotWriter
{
    public function __construct(
        private MechanicalScoringService $scorer
    ) {}

    /**
     * Extract article fields from the idea and run the mechanical scorer,
     * then persist the payload onto content_ideas.mechanical_scores_snapshot.
     *
     * @return array On success the full snapshot payload (including captured_at).
     *               On missing-article error, ['error' => '...']. Never throws.
     */
    public function captureFor(ContentIdea $idea): array
    {
        $article = $idea->generated_article ?? [];

        // Mirror the field-resolution chain used by the existing live endpoint
        // at /automation/content-ideas/{id}/mechanical-scores (routes/api.php L566-569)
        // so snapshot content matches what that endpoint would have returned.
        $title = data_get($article, 'title', $idea->title ?? '');
        $content = data_get($article, 'content', '');
        $keyword = data_get($article, 'keyword', data_get($article, 'prep_data.keyword', ''));
        $language = data_get($article, 'language', 'id');

        if (trim((string) $title) === '' || trim((string) $content) === '') {
            return [
                'error' => 'Article title or content missing — mechanical snapshot skipped.',
            ];
        }

        $scores = $this->scorer->analyze($title, $content, (string) $keyword, [
            'language' => $language,
            'current_year' => (int) date('Y'),
        ]);

        $payload = array_merge(
            ['captured_at' => now()->toIso8601String()],
            $scores
        );

        $idea->update(['mechanical_scores_snapshot' => $payload]);

        return $payload;
    }
}
