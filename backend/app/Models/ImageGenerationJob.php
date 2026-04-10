<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageGenerationJob extends Model
{
    protected $fillable = [
        'post_id', 'uuid', 'type', 'prompt',
        'insert_after_heading', 'status',
        'image_url', 'remote_url', 'error_message',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
