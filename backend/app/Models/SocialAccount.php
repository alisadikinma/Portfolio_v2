<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    use HasFactory;

    protected $table = 'social_accounts';

    protected $fillable = [
        'platform', 'platform_user_id', 'username', 'access_token',
        'refresh_token', 'token_expires_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function isTokenExpired(): bool
    {
        if ($this->token_expires_at === null) return false;
        return now()->isAfter($this->token_expires_at);
    }
}
