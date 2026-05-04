<?php

namespace App\Http\Resources\Cv;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CvAwardResource — JSON Resume `awards` shape for the CV Master Export.
 *
 * Schema mapping:
 *   awards.title         → title
 *   awards.organization  → awarder
 *   awards.received_at   → date (Y-m-d when full date present, Y-01-01 for
 *                          year-only strings like "2019")
 *   awards.description   → summary
 *   awards.is_featured   → is_featured
 *
 * Tags are not stored at the schema level for awards; we leave the field as
 * an empty array for now (the field exists in the contract for forward
 * compat — when a tags column lands later, this is the only edit needed).
 */
class CvAwardResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'awarder' => $this->organization,
            'date' => $this->formatDate($this->received_at),
            'summary' => $this->description,
            'tags' => [],
            'is_featured' => (bool) ($this->is_featured ?? false),
        ];
    }

    /**
     * Awards.received_at is a string column that operators have used both as
     * full ISO date ("2019-05-12") and as year-only ("2019"). Normalize to
     * Y-m-d for JSON Resume consumers.
     */
    protected function formatDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $str = (string) $value;
        if (preg_match('/^\d{4}$/', $str)) {
            return $str . '-01-01';
        }
        try {
            return \Carbon\Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
