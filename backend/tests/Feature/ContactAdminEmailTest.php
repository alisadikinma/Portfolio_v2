<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ContactController;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactAdminEmailTest extends TestCase
{
    private function notify(): void
    {
        $contact = new Contact(['name' => 'Budi', 'email' => 'budi@example.com', 'subject' => 'Halo', 'message' => 'Pesan']);
        $method = new \ReflectionMethod(ContactController::class, 'sendEmailNotification');
        $method->invoke(new ContactController, $contact);
    }

    public function test_notification_goes_to_configured_admin_email(): void
    {
        config(['mail.admin_email' => 'admin@alisadikinma.com']);
        Mail::shouldReceive('send')->once()->withArgs(fn ($view) => $view === 'emails.contact-notification');

        $this->notify();
    }

    public function test_no_admin_email_logs_warning_and_skips_send(): void
    {
        config(['mail.admin_email' => null]);
        Mail::shouldReceive('send')->never();
        Log::shouldReceive('warning')->once()->withArgs(fn ($m) => str_contains($m, 'admin email'));
        Log::shouldReceive('error')->never();

        $this->notify();
    }
}
