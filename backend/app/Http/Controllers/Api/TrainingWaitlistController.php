<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingWaitlistRequest;
use App\Models\Newsletter;
use App\Models\TrainingWaitlist;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TrainingWaitlistController extends Controller
{
    public function store(StoreTrainingWaitlistRequest $request): JsonResponse
    {
        // Honeypot: bots fill the hidden `website` field. Fake success, store nothing.
        if ($request->filled('website')) {
            Log::info('Training waitlist honeypot triggered', ['ip' => $request->ip()]);

            return response()->json(['success' => true, 'data' => ['id' => 0]], 201);
        }

        $data = $request->validated();
        unset($data['website']);
        $data['newsletter_opt_in'] = (bool) ($data['newsletter_opt_in'] ?? false);

        $existing = TrainingWaitlist::where('client_key', $data['client_key'])->first();
        if ($existing) {
            return response()->json(['success' => true, 'data' => ['id' => $existing->id]], 200);
        }

        try {
            $entry = TrainingWaitlist::create($data);
            $status = 201;
        } catch (QueryException $e) {
            // Concurrent double-submit lost the unique(client_key) race: return the winner.
            $entry = TrainingWaitlist::where('client_key', $data['client_key'])->first();
            if (! $entry) {
                throw $e;
            }
            $status = 200;
        }

        if ($status === 201 && $entry->newsletter_opt_in) {
            $this->subscribeToNewsletter($entry);
        }

        return response()->json(['success' => true, 'data' => ['id' => $entry->id]], $status);
    }

    private function subscribeToNewsletter(TrainingWaitlist $entry): void
    {
        // WhatsApp is UNIQUE on newsletters; skip silently if another subscriber owns it.
        if (Newsletter::where('whatsapp_number', $entry->whatsapp_number)
            ->where('email', '!=', $entry->email)->exists()) {
            return;
        }

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
    }
}
