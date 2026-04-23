<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkedInAccount extends Model
{
    use HasFactory;

    protected $table = 'linkedin_accounts';

    protected $fillable = [
        'person_urn',
        'display_name',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'last_refreshed_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(LinkedInPost::class);
    }

    public function isAccessTokenExpired(): bool
    {
        return $this->access_token_expires_at !== null
            && now()->isAfter($this->access_token_expires_at);
    }

    public function isRefreshTokenExpired(): bool
    {
        return $this->refresh_token_expires_at !== null
            && now()->isAfter($this->refresh_token_expires_at);
    }

    /**
     * True when the access_token will expire within $thresholdDays — used
     * by the daily token-refresh cron to proactively rotate.
     */
    public function needsRefresh(int $thresholdDays = 7): bool
    {
        if ($this->access_token_expires_at === null) {
            return false;
        }
        return now()->addDays($thresholdDays)->isAfter($this->access_token_expires_at);
    }
}
