<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageGenerationJob extends Model
{
    protected $fillable = [
        'post_id', 'linkedin_post_id', 'slide_index', 'slide_image_role',
        'uuid', 'type', 'prompt',
        'insert_after_heading', 'planned_filename', 'status',
        'image_url', 'remote_url', 'error_message',
    ];

    protected $casts = [
        'slide_index' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function linkedinPost(): BelongsTo
    {
        return $this->belongsTo(LinkedInPost::class, 'linkedin_post_id');
    }
}
