<?php

namespace App\Services;

use App\Models\Post;
use App\Models\ShortLink;
use Illuminate\Support\Str;

/**
 * Branded URL shortener for cross-post pipeline.
 *
 * Resolves `https://alisadikinma.com/r/{code}` for a blog post + platform tuple.
 * Idempotent — same `(post_id, source_platform)` returns existing code.
 *
 * UTM parameters are appended to `target_url` BEFORE storage so the redirect
 * carries them to Google Analytics. Short URL itself stays clean (operator-facing).
 *
 * Recognized platforms: linkedin / instagram / tiktok / threads / facebook.
 * Unknown platforms still get a short URL but no UTM (caller responsibility).
 */
class ShortLinkService
{
    private const KNOWN_PLATFORMS = [
        'linkedin',
        'instagram',
        'tiktok',
        'threads',
        'facebook',
    ];

    /**
     * Resolve (or lazily create) the short URL for a blog post on a given platform.
     *
     * @param  Post|int  $post  Post model or post_id
     * @param  string    $platform  One of the recognized platforms; pass null for ad-hoc.
     * @return string  Full short URL (https://alisadikinma.com/r/abc1234)
     */
    public function forBlogPost(Post|int $post, string $platform): string
    {
        $postId = $post instanceof Post ? (int) $post->id : (int) $post;
        $platform = strtolower(trim($platform));

        $existing = ShortLink::query()
            ->where('post_id', $postId)
            ->where('source_platform', $platform)
            ->first();

        if ($existing !== null) {
            return $existing->shortUrl();
        }

        $postModel = $post instanceof Post ? $post : Post::find($postId);
        if ($postModel === null) {
            throw new \InvalidArgumentException("Post not found: id={$postId}");
        }

        $blogUrl = $this->buildBlogUrl($postModel);
        $targetUrl = $this->appendUtm($blogUrl, $platform);
        $code = $this->generateUniqueCode();

        $row = ShortLink::create([
            'code' => $code,
            'target_url' => $targetUrl,
            'post_id' => $postId,
            'source_platform' => in_array($platform, self::KNOWN_PLATFORMS, true) ? $platform : null,
        ]);

        return $row->shortUrl();
    }

    /**
     * Resolve OR create for an arbitrary URL (not tied to a blog post).
     * Use sparingly — main path is forBlogPost().
     */
    public function forUrl(string $targetUrl, ?string $platform = null): string
    {
        $platform = $platform !== null ? strtolower(trim($platform)) : null;

        $row = ShortLink::create([
            'code' => $this->generateUniqueCode(),
            'target_url' => $platform !== null ? $this->appendUtm($targetUrl, $platform) : $targetUrl,
            'post_id' => null,
            'source_platform' => $platform !== null && in_array($platform, self::KNOWN_PLATFORMS, true)
                ? $platform
                : null,
        ]);

        return $row->shortUrl();
    }

    /**
     * Increment hit counter on redirect. Called by ShortLinkController.
     * Non-throwing — logging-grade, never fails the redirect.
     */
    public function recordHit(ShortLink $link): void
    {
        try {
            $link->increment('hits');
            $link->forceFill(['last_hit_at' => now()])->save();
        } catch (\Throwable) {
            // Cosmetic counter — never block the redirect on DB write failure
        }
    }

    private function buildBlogUrl(Post $post): string
    {
        $base = rtrim((string) config('app.url', 'https://alisadikinma.com'), '/');
        return $base . '/blog/' . $post->slug;
    }

    private function appendUtm(string $url, string $platform): string
    {
        if (!in_array($platform, self::KNOWN_PLATFORMS, true)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $params = http_build_query([
            'utm_source' => $platform,
            'utm_medium' => 'social',
            'utm_campaign' => 'cross-post',
        ]);

        return $url . $separator . $params;
    }

    /**
     * Generate a unique base62 code. 7 chars yields ~3.5T combos — collisions
     * are essentially never at any realistic volume. Single-pass retry on the
     * astronomically-rare collision event.
     */
    private function generateUniqueCode(int $length = 7, int $maxAttempts = 5): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = $this->randomBase62($length);
            if (!ShortLink::where('code', $code)->exists()) {
                return $code;
            }
        }
        // After 5 collisions on 7-char codes, expand length defensively.
        return $this->randomBase62($length + 2);
    }

    private function randomBase62(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        $alphabetLength = strlen($alphabet);
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $alphabetLength - 1)];
        }
        return $code;
    }
}
