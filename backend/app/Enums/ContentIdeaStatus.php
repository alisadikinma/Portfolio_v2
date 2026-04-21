<?php

namespace App\Enums;

enum ContentIdeaStatus: string
{
    case Draft = 'draft';
    case Researching = 'researching';
    case ArticleReady = 'article_ready';
    case AwaitingManualUpload = 'awaiting_manual_upload';
    case GeneratingImages = 'generating_images';
    case ImagesReady = 'images_ready';
    case Completed = 'completed';
    case Failed = 'failed';
    case Archived = 'archived';

    public const TRANSITIONS = [
        // Self-transitions: 'researching' → 'researching' allowed for
        // admin Resume (re-enter prep/write/score). Same for
        // generating_images when ImageGenerationService re-dispatches
        // remaining segments.
        //
        // Revert-to-draft paths: article_ready → draft and images_ready →
        // draft support the admin "Revert to Draft" action which clears
        // research_data + generated_article to restart from scratch.
        //
        // article_ready → completed: edge path when an article has no
        // image_prompts to dispatch (rare — carousel-only output, etc.).
        'draft' => ['researching', 'archived'],
        'researching' => ['researching', 'article_ready', 'failed', 'awaiting_manual_upload'],
        'awaiting_manual_upload' => ['generating_images', 'failed', 'archived'],
        // article_ready → researching allowed for admin "runDeepScore":
        // re-runs the Sonnet scorer on an already-written article; the
        // idea briefly drops back to researching while the async step
        // executes, then transitions forward again.
        'article_ready' => ['researching', 'generating_images', 'completed', 'failed', 'archived', 'draft'],
        'generating_images' => ['generating_images', 'images_ready', 'completed', 'failed', 'awaiting_manual_upload'],
        'images_ready' => ['completed', 'archived', 'draft'],
        // completed → researching | generating_images: admin-triggered
        // Regenerate on a Completed idea. The live Post stays at /blog/{slug}
        // during regen — ContentPublishService uses Post::updateOrCreate keyed
        // on id when the user re-publishes, rewriting the existing Post in
        // place instead of orphaning + creating a new one.
        'completed' => ['archived', 'researching', 'generating_images'],
        'failed' => ['researching', 'article_ready', 'generating_images', 'archived', 'draft'],
        'archived' => ['draft'],
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }
}
