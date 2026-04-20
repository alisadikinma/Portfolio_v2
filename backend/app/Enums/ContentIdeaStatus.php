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
        'draft' => ['researching', 'archived'],
        'researching' => ['article_ready', 'failed', 'awaiting_manual_upload'],
        'awaiting_manual_upload' => ['generating_images', 'failed', 'archived'],
        'article_ready' => ['generating_images', 'failed', 'archived'],
        'generating_images' => ['images_ready', 'completed', 'failed', 'awaiting_manual_upload'],
        'images_ready' => ['completed', 'archived'],
        'completed' => ['archived'],
        'failed' => ['researching', 'article_ready', 'generating_images', 'archived'],
        'archived' => ['draft'],
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }
}
