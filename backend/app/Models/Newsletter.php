<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'is_subscribed',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /**
     * Scope a query to only include subscribed users.
     */
    public function scopeSubscribed($query)
    {
        return $query->where('is_subscribed', true);
    }

    /**
     * Subscribe the user.
     */
    public function subscribe()
    {
        $this->update([
            'is_subscribed' => true,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    /**
     * Unsubscribe the user.
     */
    public function unsubscribe()
    {
        $this->update([
            'is_subscribed' => false,
            'unsubscribed_at' => now(),
        ]);
    }
}
