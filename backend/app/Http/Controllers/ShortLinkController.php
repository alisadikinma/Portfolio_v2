<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use App\Services\ShortLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public shortener redirect — `GET /r/{code}` → 301 to ShortLink.target_url.
 *
 * Hit counter incremented async-friendly (non-blocking on DB write failure).
 * Unknown codes 404 — does NOT leak existence of any code structure.
 */
class ShortLinkController extends Controller
{
    public function __construct(private readonly ShortLinkService $service)
    {
    }

    public function redirect(Request $request, string $code): RedirectResponse
    {
        $link = ShortLink::where('code', $code)->first();
        if ($link === null) {
            abort(404);
        }

        $this->service->recordHit($link);

        // 301 permanent — short URL → blog URL is a stable mapping.
        // Browsers + CDNs cache this aggressively which is desirable.
        return redirect()->away($link->target_url, 301);
    }
}
