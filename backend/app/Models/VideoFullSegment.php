<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per segment of a `video_full` reel (talking / b-roll / split-screen).
 * Mirror of repurpose_video_slides but for the talking-head timeline; the
 * MacBook worker writes status/preview/clip back via the bridge API.
 */
class VideoFullSegment extends Model
{
    public const TYPE_TO_CAMERA = 'to_camera';
    public const TYPE_B_ROLL = 'b_roll';
    public const TYPE_SPLIT_SCREEN = 'split_screen';

    protected $fillable = [
        'repurpose_job_id', 'segment_index', 'type', 'start_sec', 'end_sec',
        'source_text_en', 'text_id', 'strategy', 'status', 'provider',
        'preview_url', 'clip_path', 'last_error',
    ];

    protected $casts = [
        'segment_index' => 'integer',
        'start_sec' => 'float',
        'end_sec' => 'float',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(RepurposeJob::class, 'repurpose_job_id');
    }
}
