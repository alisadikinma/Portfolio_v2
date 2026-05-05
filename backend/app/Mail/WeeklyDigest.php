<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WeeklyDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Collection $featuredProjects;

    public function __construct(
        public Collection $posts,
        public Newsletter $subscriber,
    ) {
        // Up to 3 featured projects per digest, picked by sort_order then id.
        // Empty collection if no rows / schema mismatch — template @forelse
        // skips the whole section silently when nothing to show.
        try {
            $this->featuredProjects = Project::query()
                ->where('featured', true)
                ->where('published', true)
                ->where('is_active', true)
                ->orderByDesc('sort_order')
                ->orderByDesc('id')
                ->limit(3)
                ->get();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WeeklyDigest featured projects lookup failed', [
                'error' => $e->getMessage(),
            ]);
            $this->featuredProjects = collect();
        }
    }

    public function envelope(): Envelope
    {
        $count = $this->posts->count();

        return new Envelope(
            subject: "Friday Digest · {$count} reads from this week",
            from: new Address(
                config('mail.from.address', 'aiagent@alisadikinma.com'),
                config('mail.from.name', 'Ali Sadikin'),
            ),
            replyTo: [
                new Address(
                    config('mail.reply_to.address', 'aiagent@alisadikinma.com'),
                    config('mail.reply_to.name', 'Ali Sadikin'),
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-digest',
            text: 'emails.weekly-digest-text',
            with: [
                'posts' => $this->posts,
                'subscriber' => $this->subscriber,
                'featuredProjects' => $this->featuredProjects,
                'campaign' => 'weekly-' . now()->format('Y-W'),
            ],
        );
    }
}
