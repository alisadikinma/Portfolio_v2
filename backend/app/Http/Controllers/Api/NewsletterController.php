<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION', 'message' => $validator->errors()->first()],
            ], 422);
        }

        $existing = Newsletter::where('email', $request->email)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'DUPLICATE', 'message' => 'This email is already subscribed.'],
            ], 409);
        }

        Newsletter::create(['email' => $request->email]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed to the newsletter!',
        ], 201);
    }

    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
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
}
