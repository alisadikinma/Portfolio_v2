<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'organization',
        'image',
        'received_at',
        'order',
    ];

    protected $casts = [
        'received_at' => 'date',
    ];
}
