<?php

/**
 * Vestigial helper placeholder.
 *
 * `composer.json` has referenced `app/Helpers/ImageHelper.php` in its
 * `autoload.files` array since the first commit, but the file was never
 * committed and nothing in the codebase references an `ImageHelper`. On the
 * production VPS `composer install` simply skips the missing entry, so prod
 * works; locally a stale dumped autoload eagerly `require`s it and fatals.
 *
 * This empty no-op file satisfies the autoload entry without introducing any
 * behavior. If a real image helper is ever needed, add functions here.
 */
