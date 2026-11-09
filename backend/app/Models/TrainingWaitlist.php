<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingWaitlist extends Model
{
    use HasFactory;

    protected $table = 'training_waitlist';

    protected $fillable = [
        'name',
        'company',
        'email',
        'whatsapp_number',
        'interest',
        'note',
        'source_page',
        'locale',
        'newsletter_opt_in',
        'status',
        'admin_note',
        'client_key',
    ];

    protected $attributes = [
        'status' => 'new',
        'newsletter_opt_in' => false,
    ];

    protected $casts = [
        'newsletter_opt_in' => 'boolean',
    ];
}
