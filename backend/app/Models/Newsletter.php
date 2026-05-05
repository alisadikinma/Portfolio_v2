<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Newsletter extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'whatsapp_number',
        'unsubscribe_token',
        'consent_given_at',
        'source',
        'is_subscribed',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'consent_given_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->unsubscribe_token)) {
                $model->unsubscribe_token = Str::random(32);
            }
        });
    }

    public function scopeSubscribed($query)
    {
        return $query->where('is_subscribed', true);
    }

    public function subscribe(): void
    {
        $this->update([
            'is_subscribed' => true,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    public function unsubscribe(): void
    {
        $this->update([
            'is_subscribed' => false,
            'unsubscribed_at' => now(),
        ]);
    }
}
