<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingWaitlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingWaitlistAdminController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'cancelled'];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $page = $this->filtered($request)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => array_map(fn ($r) => $this->present($r), $page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'links' => [
                'first' => $page->url(1),
                'last' => $page->url($page->lastPage()),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = TrainingWaitlist::find($id);
        if (!$row) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Waitlist entry not found.'],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|required|in:' . implode(',', self::STATUSES),
            'admin_note' => 'sometimes|nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $validator->errors()->first(),
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        // Only these two fields are editable; everything else is ignored.
        $row->fill($validator->validated())->save();

        return response()->json([
            'success' => true,
            'data' => $this->present($row->fresh()),
            'message' => 'Waitlist entry updated.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'training-waitlist-' . now('Asia/Jakarta')->format('Y-m-d') . '.csv';
        $query = $this->filtered($request);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'company', 'email', 'whatsapp', 'interest', 'locale', 'status', 'created_at']);

            $query->reorder()->chunkById(500, function ($rows) use ($handle) {
                /** @var \Illuminate\Support\Collection<int, TrainingWaitlist> $rows */
                foreach ($rows as $r) {
                    fputcsv($handle, array_map([$this, 'safeCell'], [
                        $r->name,
                        $r->company,
                        $r->email,
                        $r->whatsapp_number,
                        $r->interest,
                        $r->locale,
                        $r->status,
                        $r->created_at?->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                    ]));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Neutralise spreadsheet formula injection. */
    private function safeCell(mixed $value): string
    {
        $s = (string) ($value ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $s;
        }

        return $s;
    }

    /** @return Builder<TrainingWaitlist> */
    private function filtered(Request $request): Builder
    {
        $query = TrainingWaitlist::query();

        if (($search = trim((string) $request->query('search'))) !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('company', 'like', $like);
            });
        }

        foreach (['interest', 'locale', 'status'] as $field) {
            $val = $request->query($field);
            if (is_string($val) && $val !== '') {
                $query->where($field, $val);
            }
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function present(\Illuminate\Database\Eloquent\Model $r): array
    {
        return $r->makeHidden(['client_key'])->toArray();
    }
}
