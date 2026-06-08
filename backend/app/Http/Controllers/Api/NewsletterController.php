<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    private const SOURCE_VALUES = [
        'blog_inline',
        'inline_card',
        'floating_banner',
        'footer_bar',
        'homepage_join',
    ];

    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'whatsapp_number' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::SOURCE_VALUES)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION', 'message' => $validator->errors()->first()],
            ], 422);
        }

        $existing = Newsletter::where('email', $request->email)
            ->orWhere('whatsapp_number', $request->whatsapp_number)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'DUPLICATE', 'message' => 'This email or WhatsApp number is already subscribed.'],
            ], 409);
        }

        Newsletter::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'whatsapp_number' => $request->input('whatsapp_number'),
            'source' => $request->input('source'),
            'consent_given_at' => now(),
            'is_subscribed' => true,
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed to the newsletter!',
        ], 201);
    }

    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION', 'message' => $validator->errors()->first()],
            ], 422);
        }

        $subscriber = Newsletter::where('email', $request->email)->first();
        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Email not found in subscribers list.'],
            ], 404);
        }

        $subscriber->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully unsubscribed.',
        ]);
    }

    public function unsubscribeByToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'size:32'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION', 'message' => 'Invalid unsubscribe link.'],
            ], 422);
        }

        $subscriber = Newsletter::where('unsubscribe_token', $request->token)->first();
        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'This unsubscribe link is no longer valid.'],
            ], 404);
        }

        $subscriber->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully unsubscribed.',
        ]);
    }
}
