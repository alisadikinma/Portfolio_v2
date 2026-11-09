<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingWaitlistRequest;
use App\Models\Newsletter;
use App\Models\TrainingWaitlist;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PF-2 public training waitlist.
 *
 * NOTE (Phase 4C): any CSV export of this table MUST escape cells that start
 * with = + - @ (formula injection), e.g. by prefixing a single quote.
 * Response bodies expose only the client-generated client_key, never the
 * sequential row id.
 */
class TrainingWaitlistController extends Controller
{
    public function store(StoreTrainingWaitlistRequest $request): JsonResponse
    {
        // Honeypot: bots fill the hidden `website` field. Fake success, store nothing.
        // Body is identical to a real 201 so it cannot be told apart.
        if ($request->filled('website')) {
            Log::info('Training waitlist honeypot triggered', ['ip' => $request->ip()]);

            $key = (string) $request->input('client_key');

            return $this->ok(Str::isUuid($key) ? $key : (string) Str::uuid(), 201);
        }

        $data = $request->validated();
        unset($data['website']);
        $data['newsletter_opt_in'] = (bool) ($data['newsletter_opt_in'] ?? false);

        if (TrainingWaitlist::where('client_key', $data['client_key'])->exists()) {
            return $this->ok($data['client_key'], 200);
        }

        try {
            $entry = TrainingWaitlist::create($data);
        } catch (UniqueConstraintViolationException) {
            // Concurrent double-submit lost the unique(client_key) race: the winner
            // exists. Nothing is logged here: the exception message embeds SQL bindings (PII).
            $winner = TrainingWaitlist::where('client_key', $data['client_key'])->first();
            if ($winner === null) {
                // Different unique violation: rethrow sanitized (no bindings).
                throw new \RuntimeException('Training waitlist insert violated a unique constraint.');
            }

            return $this->ok($data['client_key'], 200);
        }

        if ($entry->newsletter_opt_in) {
            $this->subscribeToNewsletter($entry);
        }

        return $this->ok($entry->client_key, 201);
    }

    private function ok(string $clientKey, int $status): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['client_key' => $clientKey]], $status);
    }

    private function subscribeToNewsletter(TrainingWaitlist $entry): void
    {
        // WhatsApp is UNIQUE on newsletters; skip if another subscriber owns it.
        if (Newsletter::where('whatsapp_number', $entry->whatsapp_number)
            ->where('email', '!=', $entry->email)->exists()) {
            Log::info('Training waitlist newsletter skipped: whatsapp owned by another subscriber', [
                'waitlist_id' => $entry->id,
            ]);

            return;
        }

        try {
            Newsletter::firstOrCreate(
                ['email' => $entry->email],
                [
                    'name' => $entry->name,
                    'whatsapp_number' => $entry->whatsapp_number,
                    'source' => 'training_waitlist',
                    'consent_given_at' => now(),
                    'is_subscribed' => true,
                    'subscribed_at' => now(),
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // Never fail the waitlist submit over the newsletter side effect.
            Log::warning('Training waitlist newsletter subscribe hit a unique constraint', [
                'waitlist_id' => $entry->id,
            ]);
        }
    }
}
