<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WeeklyDigest;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NewsletterAdminController extends Controller
{
    private const ALLOWED_SOURCES = [
        'blog_inline',
        'inline_card',
        'floating_banner',
        'footer_bar',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Newsletter::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($source = $request->query('source')) {
            if (in_array($source, self::ALLOWED_SOURCES, true)) {
                $query->where('source', $source);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $page = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $subscriber = Newsletter::find($id);
        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Subscriber not found.'],
            ], 404);
        }

        $subscriber->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscriber removed.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'newsletter-subscribers-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'WhatsApp', 'Source', 'Subscribed At']);

            Newsletter::orderByDesc('created_at')->chunkById(500, function ($subs) use ($handle) {
                foreach ($subs as $sub) {
                    fputcsv($handle, [
                        $sub->name ?? '',
                        $sub->email,
                        $sub->whatsapp_number ?? '',
                        $sub->source ?? '',
                        optional($sub->created_at)->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(): JsonResponse
    {
        $posts = Post::published()
            ->whereBetween('published_at', [now()->subWeek(), now()])
            ->with(['category', 'translations'])
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        $fakeSubscriber = Newsletter::factory()->make([
            'unsubscribe_token' => str_repeat('x', 32),
            'name' => 'Preview Recipient',
            'email' => 'preview@example.com',
        ]);

        $html = (new WeeklyDigest($posts, $fakeSubscriber))->render();

        return response()->json([
            'success' => true,
            'data' => [
                'html' => $html,
                'posts_count' => $posts->count(),
                'subscriber_count' => Newsletter::count(),
                'campaign' => 'weekly-' . now()->format('Y-W'),
            ],
        ]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipient' => ['nullable', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION', 'message' => $validator->errors()->first()],
            ], 422);
        }

        $recipient = $request->input('recipient') ?? $request->user()?->email;
        if (!$recipient) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_RECIPIENT', 'message' => 'No recipient email available.'],
            ], 422);
        }

        $posts = Post::published()
            ->whereBetween('published_at', [now()->subWeek(), now()])
            ->with(['category', 'translations'])
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        $fakeSubscriber = Newsletter::factory()->make([
            'unsubscribe_token' => str_repeat('x', 32),
            'name' => 'Test Recipient',
            'email' => $recipient,
        ]);

        $startedAt = now();

        try {
            Mail::to($recipient)->send(new WeeklyDigest($posts, $fakeSubscriber));
        } catch (Throwable $e) {
            NewsletterSend::create([
                'sent_at' => $startedAt,
                'subscriber_count' => 0,
                'posts_count' => $posts->count(),
                'post_ids' => $posts->pluck('id')->toArray(),
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 65000),
                'triggered_by' => 'test',
                'created_by_user_id' => $request->user()?->id,
                'test_recipient' => $recipient,
                'duration_seconds' => $startedAt->diffInSeconds(now()),
            ]);

            return response()->json([
                'success' => false,
                'error' => ['code' => 'SEND_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }

        NewsletterSend::create([
            'sent_at' => $startedAt,
            'subscriber_count' => 1,
            'posts_count' => $posts->count(),
            'post_ids' => $posts->pluck('id')->toArray(),
            'status' => 'sent',
            'triggered_by' => 'test',
            'created_by_user_id' => $request->user()?->id,
            'test_recipient' => $recipient,
            'duration_seconds' => $startedAt->diffInSeconds(now()),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Test digest sent to {$recipient}.",
        ]);
    }

    public function sendNow(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        Artisan::queue('newsletter:send-weekly', [
            '--triggered-by' => 'manual',
            '--user-id' => $userId,
            '--force' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Newsletter digest queued for delivery.',
        ], 202);
    }

    public function sends(Request $request): JsonResponse
    {
        $query = NewsletterSend::query();

        if ($status = $request->query('status')) {
            if (in_array($status, ['sent', 'failed', 'skipped', 'partial'], true)) {
                $query->where('status', $status);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $page = $query->orderByDesc('sent_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function showSend(int $id): JsonResponse
    {
        $send = NewsletterSend::with('createdBy')->find($id);
        if (!$send) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Send record not found.'],
            ], 404);
        }

        $postIds = $send->post_ids ?? [];
        $posts = empty($postIds)
            ? collect()
            : Post::whereIn('id', $postIds)->with('translations')->get()->map(function ($post) {
                $translation = $post->translations->first();
                return [
                    'id' => $post->id,
                    'slug' => $post->slug,
                    'title' => $translation?->title ?? $post->slug,
                    'published_at' => $post->published_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $send->id,
                'sent_at' => $send->sent_at?->toIso8601String(),
                'subscriber_count' => $send->subscriber_count,
                'posts_count' => $send->posts_count,
                'status' => $send->status,
                'error_message' => $send->error_message,
                'triggered_by' => $send->triggered_by,
                'test_recipient' => $send->test_recipient,
                'duration_seconds' => $send->duration_seconds,
                'created_by' => $send->createdBy ? [
                    'id' => $send->createdBy->id,
                    'name' => $send->createdBy->name,
                    'email' => $send->createdBy->email,
                ] : null,
                'posts' => $posts,
            ],
        ]);
    }
}
