<?php

namespace App\Support;

/**
 * mkdir + FORCE group-write (0775) for directories shared across the two
 * users in the video_rebrand pipeline.
 *
 * PHP's mkdir() mode is masked by the process umask (php-fpm runs umask 022 →
 * 0755), which strips the group-write bit. But the repurpose pipeline writes
 * across TWO users: www-data (php-fpm, initial keyframe/Veo/compose render) and
 * claudesn (queue worker, the credit-free hook/CTA re-skin). Both belong to the
 * www-data group, so the shared dirs MUST stay group-writable or the second
 * user hits ffmpeg "Permission denied" on output (the exact cause of job #26's
 * hook re-skin always failing — the dir was created 0755 by www-data, claudesn
 * could not write into it).
 *
 * chmod() ignores umask, so calling it right after mkdir guarantees 0775
 * regardless of which user (or umask) creates the dir first. The chmod is
 * best-effort (@) — when the dir already exists owned by the OTHER user it's a
 * silent no-op, which is correct: whoever created it first already set 0775.
 */
class SharedDir
{
    public static function ensure(string $absDir): void
    {
        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        @chmod($absDir, 0775);
    }
}
