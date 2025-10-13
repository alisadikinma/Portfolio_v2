<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    /**
     * Store a new contact message and send email notification.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        try {
            // Create contact record
            $contact = Contact::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'subject' => $request->input('subject'),
                'message' => $request->input('message'),
                'is_read' => false,
            ]);

            // Send email notification
            $this->sendEmailNotification($contact);

            return response()->json([
                'message' => 'Thank you for your message! We will get back to you soon.',
                'data' => new ContactResource($contact),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create contact message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to send message. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Display a listing of contact messages (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contact::query();

        // Filter by read status
        if ($request->has('is_read')) {
            $isRead = filter_var($request->query('is_read'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_read', $isRead);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Order by
        $query->orderBy('created_at', 'desc');

        $contacts = $query->paginate(20);

        return response()->json([
            'data' => ContactResource::collection($contacts),
            'links' => [
                'first' => $contacts->url(1),
                'last' => $contacts->url($contacts->lastPage()),
                'prev' => $contacts->previousPageUrl(),
                'next' => $contacts->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'from' => $contacts->firstItem(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'to' => $contacts->lastItem(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    /**
     * Display the specified contact message (Admin only).
     */
    public function show(string $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);

        // Mark as read if not already read
        if (!$contact->is_read) {
            $contact->markAsRead();
        }

        return response()->json([
            'data' => new ContactResource($contact),
        ]);
    }

    /**
     * Mark contact message as read (Admin only).
     */
    public function markAsRead(string $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        $contact->markAsRead();

        return response()->json([
            'message' => 'Contact marked as read',
            'data' => new ContactResource($contact),
        ]);
    }

    /**
     * Delete contact message (Admin only).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $contact = Contact::findOrFail($id);
            $contact->delete();

            return response()->json([
                'message' => 'Contact deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send email notification for new contact.
     */
    private function sendEmailNotification(Contact $contact): void
    {
        try {
            // Get admin email from config
            $adminEmail = config('mail.admin_email', env('ADMIN_EMAIL', 'admin@example.com'));

            // Send email using Laravel Mail
            Mail::send('emails.contact-notification', ['contact' => $contact], function ($message) use ($adminEmail, $contact) {
                $message->to($adminEmail)
                    ->subject('New Contact Message: ' . $contact->subject)
                    ->replyTo($contact->email, $contact->name);
            });
        } catch (\Exception $e) {
            // Log error but don't throw - contact should still be saved
            Log::error('Failed to send contact notification email: ' . $e->getMessage());
        }
    }
}
