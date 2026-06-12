<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One carousel slide of a video_rebrand RepurposeJob — see
 * docs/plans/2026-06-12-ig-video-carousel-rebrand.md (Phase A).
 */
class RepurposeVideoSlide extends Model
{
    use HasFactory;

    public const ROLE_HOOK = 'hook';
    public const ROLE_TOOL = 'tool';
    public const ROLE_CTA = 'cta';

    protected $fillable = [
        'repurpose_job_id',
        'slide_index',
        'role',
        'source_video_path',
        'poster_path',
        'header_title',
        'header_desc',
        'crop_y',
        'crop_h',
        'keyframe_job_uuid',
        'keyframe_status',
        'keyframe_url',
        'veo_job_uuid',
        'veo_status',
        'veo_url',
        'composited_path',
        'composited_status',
        'last_error',
    ];

    protected $casts = [
        'slide_index' => 'integer',
        'crop_y' => 'integer',
        'crop_h' => 'integer',
    ];

    public function repurposeJob(): BelongsTo
    {
        return $this->belongsTo(RepurposeJob::class);
    }
}
