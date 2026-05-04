<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Hero stats block for /api/homepage/stats and the embedded `stats`
 * key inside /api/homepage/featured. The underlying data is computed
 * in HomepageController::computeStats and passed in as an array — this
 * resource just enforces the contract so future changes (adding a key,
 * formatting a number) live in one place.
 */
class HomepageStatsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'years_experience' => (int) ($this->resource['years_experience'] ?? 0),
            'awards_count' => (int) ($this->resource['awards_count'] ?? 0),
            'projects_count' => (int) ($this->resource['projects_count'] ?? 0),
            'enterprise_brands' => array_values($this->resource['enterprise_brands'] ?? []),
        ];
    }
}
