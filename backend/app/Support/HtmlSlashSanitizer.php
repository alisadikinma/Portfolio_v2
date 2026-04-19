<?php

namespace App\Support;

/**
 * Strips JSON-escape leakage from HTML content stored by the content-engine
 * pipeline. The plugin side sometimes double-escapes slashes so literal `\/`
 * survives into the DB — that turns `</p>` into `<\/p>` at render time, which
 * browsers don't recognize as a closing tag. The whole article then cascades
 * into the prior opening tag's styling (everything becomes italic H2 once an
 * unclosed <h2> runs off the end).
 *
 * This sanitizer normalizes `\/` back to `/` inside string values. It's safe
 * to run on any text content because `\/` is never a valid sequence in HTML
 * markup (and when it does appear legitimately — e.g. inside JavaScript in
 * a <script> tag — those are always served via <pre><code>, not raw HTML).
 */
class HtmlSlashSanitizer
{
    /**
     * Recursively apply the unescape to every string value in the payload.
     * Handles the known article shapes:
     *   ['title' => ..., 'content' => ..., 'id' => ['title' => ..., 'content' => ...], 'en' => ...]
     * and also plain flat arrays. Scalars that aren't strings (ints, bools)
     * pass through untouched.
     */
    public static function apply(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::apply($value);
            } elseif (is_string($value)) {
                $payload[$key] = self::unescapeSlashes($value);
            }
        }
        return $payload;
    }

    /**
     * Strip only the specific `\/` sequence. Does not touch `\\` or other
     * legitimate escape sequences. If the string contains no `\/`, returns
     * the original reference (cheap early-out for already-clean content).
     */
    public static function unescapeSlashes(string $text): string
    {
        if (strpos($text, '\\/') === false) {
            return $text;
        }
        return str_replace('\\/', '/', $text);
    }
}
