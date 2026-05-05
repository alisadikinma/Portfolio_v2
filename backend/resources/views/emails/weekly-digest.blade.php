<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ 'Friday Digest · ' . $posts->count() . ' reads' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#050506;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#EDEDEF;-webkit-font-smoothing:antialiased;">

<div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#050506;">
    {{ $subscriber->name ?? 'Hi friend' }}, here are {{ $posts->count() }} essays from this week.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#050506;padding:32px 16px;">
    <tr>
        <td align="center">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#0C0C0F;border-radius:14px;border:1px solid rgba(212,168,67,0.12);">

                <tr>
                    <td style="padding:28px 32px 12px 32px;border-bottom:1px solid rgba(255,255,255,0.06);">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="left" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;font-weight:700;color:#EDEDEF;letter-spacing:-0.01em;">
                                    alisadikinma
                                </td>
                                <td align="right" style="font-family:'SF Mono','JetBrains Mono',Consolas,Monaco,monospace;font-size:10px;font-weight:600;color:#D4A843;letter-spacing:0.18em;text-transform:uppercase;">
                                    Friday Digest
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 32px 16px 32px;">
                        <p style="margin:0 0 12px 0;font-size:18px;line-height:1.4;color:#EDEDEF;font-weight:600;">
                            Hi {{ $subscriber->name ?? 'there' }},
                        </p>
                        <p style="margin:0;font-size:15px;line-height:1.6;color:#8A8F98;">
                            @if($posts->count() === 0)
                                It's been a quiet week on the blog &mdash; no new essays just yet. Check back next Friday.
                            @else
                                Here's what landed on the blog this week &mdash; {{ $posts->count() }} {{ $posts->count() === 1 ? 'essay' : 'essays' }} you might've missed.
                            @endif
                        </p>
                    </td>
                </tr>

                @foreach($posts as $post)
                    @php
                        $translation = $post->translations->first();
                        $title = $translation?->title ?? $post->slug;
                        $rawExcerpt = $translation?->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($translation?->content ?? ''), 200);
                        $excerpt = trim($rawExcerpt) !== '' ? $rawExcerpt : 'Read the full essay on the blog.';
                        $url = 'https://alisadikinma.com/blog/' . $post->slug . '?utm_source=newsletter&utm_medium=email&utm_campaign=' . $campaign;
                        $category = $post->category?->name ?? 'Essay';
                        $featuredImage = $post->featured_image;
                    @endphp
                    <tr>
                        <td style="padding:8px 32px 8px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0E0E11;border-radius:10px;border:1px solid rgba(255,255,255,0.04);">
                                @if($featuredImage)
                                    <tr>
                                        <td style="padding:0;">
                                            <a href="{{ $url }}" style="display:block;text-decoration:none;">
                                                <img src="{{ $featuredImage }}" alt="{{ $title }}" width="536" style="display:block;width:100%;max-width:536px;height:auto;border-radius:10px 10px 0 0;border:0;outline:none;text-decoration:none;">
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:20px 24px 22px 24px;">
                                        <p style="margin:0 0 8px 0;font-family:'SF Mono','JetBrains Mono',Consolas,Monaco,monospace;font-size:10px;font-weight:600;color:#D4A843;letter-spacing:0.16em;text-transform:uppercase;">
                                            {{ $category }}
                                        </p>
                                        <h2 style="margin:0 0 10px 0;font-size:22px;line-height:1.3;color:#EDEDEF;font-weight:700;letter-spacing:-0.01em;">
                                            <a href="{{ $url }}" style="color:#EDEDEF;text-decoration:none;">{{ $title }}</a>
                                        </h2>
                                        <p style="margin:0 0 18px 0;font-size:14px;line-height:1.6;color:#8A8F98;">
                                            {{ \Illuminate\Support\Str::limit(strip_tags($excerpt), 180) }}
                                        </p>
                                        <a href="{{ $url }}" style="display:inline-block;padding:10px 18px;background-color:#D4A843;color:#050506;font-size:13px;font-weight:700;text-decoration:none;border-radius:999px;letter-spacing:0.01em;">
                                            Read this essay &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endforeach

                @if(!empty($featuredProject))
                    @php
                        $rawProjectImage = $featuredProject->image ?? '';
                        $projectImageUrl = str_starts_with($rawProjectImage, 'http')
                            ? $rawProjectImage
                            : (empty($rawProjectImage) ? null : 'https://alisadikinma.com/storage/' . ltrim($rawProjectImage, '/'));
                        $projectUrl = "https://alisadikinma.com/projects/{$featuredProject->slug}?utm_source=newsletter&utm_medium=email&utm_campaign={$campaign}";
                        $projectExcerpt = $featuredProject->impact_statement
                            ?? \Illuminate\Support\Str::limit(strip_tags($featuredProject->description ?? ''), 160);
                    @endphp
                    <tr>
                        <td style="padding:20px 32px 8px 32px;">
                            <p style="margin:0 0 10px 0;font-family:'SF Mono','JetBrains Mono',Consolas,Monaco,monospace;font-size:10px;font-weight:600;color:#06B6D4;letter-spacing:0.18em;text-transform:uppercase;">
                                Featured Project
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 8px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0E0E11;border-radius:10px;border:1px solid rgba(6,182,212,0.18);">
                                @if($projectImageUrl)
                                    <tr>
                                        <td style="padding:0;">
                                            <a href="{{ $projectUrl }}" style="display:block;text-decoration:none;">
                                                <img src="{{ $projectImageUrl }}" alt="{{ $featuredProject->title }}" width="536" style="display:block;width:100%;max-width:536px;height:auto;border-radius:10px 10px 0 0;border:0;outline:none;text-decoration:none;">
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:20px 24px 22px 24px;">
                                        @if(!empty($featuredProject->category))
                                            <p style="margin:0 0 8px 0;font-family:'SF Mono','JetBrains Mono',Consolas,Monaco,monospace;font-size:10px;font-weight:600;color:#06B6D4;letter-spacing:0.16em;text-transform:uppercase;">
                                                {{ $featuredProject->category }}
                                            </p>
                                        @endif
                                        <h2 style="margin:0 0 10px 0;font-size:22px;line-height:1.3;color:#EDEDEF;font-weight:700;letter-spacing:-0.01em;">
                                            <a href="{{ $projectUrl }}" style="color:#EDEDEF;text-decoration:none;">{{ $featuredProject->title }}</a>
                                        </h2>
                                        @if($projectExcerpt)
                                            <p style="margin:0 0 18px 0;font-size:14px;line-height:1.6;color:#8A8F98;">
                                                {{ \Illuminate\Support\Str::limit($projectExcerpt, 180) }}
                                            </p>
                                        @endif
                                        <a href="{{ $projectUrl }}" style="display:inline-block;padding:10px 18px;background-color:transparent;color:#06B6D4;font-size:13px;font-weight:700;text-decoration:none;border:1px solid #06B6D4;border-radius:999px;letter-spacing:0.01em;">
                                            View case study &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                @php
                    $waMessage = rawurlencode("Halo Ali, saya tertarik konsultasi tentang efisiensi operasional dengan AI di tempat kerja saya.");
                    $waUrl = "https://wa.me/6281380163758?text={$waMessage}";
                @endphp
                <tr>
                    <td style="padding:8px 32px 8px 32px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(135deg,#1A1611 0%,#0E0E11 100%);border-radius:12px;border:1px solid rgba(212,168,67,0.25);">
                            <tr>
                                <td style="padding:24px 26px;">
                                    <p style="margin:0 0 8px 0;font-family:'SF Mono','JetBrains Mono',Consolas,Monaco,monospace;font-size:10px;font-weight:600;color:#D4A843;letter-spacing:0.18em;text-transform:uppercase;">
                                        Konsultasi AI &middot; 1-on-1
                                    </p>
                                    <h3 style="margin:0 0 10px 0;font-size:20px;line-height:1.3;color:#EDEDEF;font-weight:700;letter-spacing:-0.01em;">
                                        Need an AI expert for your business?
                                    </h3>
                                    <p style="margin:0 0 18px 0;font-size:14px;line-height:1.6;color:#8A8F98;">
                                        Ali Sadikin adalah <strong style="color:#EDEDEF;font-weight:600;">AI Generalist Expert</strong> &mdash;
                                        diskusi langsung di WhatsApp tentang bagaimana AI bisa meningkatkan efisiensi operasional
                                        perusahaan atau tempat kerja Anda.
                                    </p>
                                    <a href="{{ $waUrl }}" style="display:inline-block;padding:12px 22px;background-color:#25D366;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;border-radius:999px;letter-spacing:0.01em;">
                                        💬 Chat di WhatsApp &rarr;
                                    </a>
                                    <p style="margin:12px 0 0 0;font-size:11px;color:#5A5F68;font-family:'SF Mono','JetBrains Mono',Consolas,Monaco,monospace;letter-spacing:0.05em;">
                                        +62 813-8016-3758
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 32px 32px 32px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:18px 0 0 0;border-top:1px solid rgba(255,255,255,0.06);">
                                    <p style="margin:0 0 14px 0;font-size:14px;line-height:1.6;color:#8A8F98;font-style:italic;">
                                        Reply to this email &mdash; I read every one.
                                    </p>
                                    <p style="margin:0 0 4px 0;font-size:14px;color:#EDEDEF;font-weight:600;">
                                        Ali Sadikin
                                    </p>
                                    <p style="margin:0;font-size:13px;color:#8A8F98;">
                                        <a href="https://alisadikinma.com" style="color:#06B6D4;text-decoration:none;">alisadikinma.com</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin-top:20px;">
                <tr>
                    <td align="center" style="padding:18px 24px;font-size:11px;line-height:1.6;color:#5A5F68;">
                        <a href="https://alisadikinma.com/newsletter/unsubscribe?token={{ $subscriber->unsubscribe_token }}" style="color:#8A8F98;text-decoration:underline;">Unsubscribe</a>
                        &nbsp;&middot;&nbsp;
                        <a href="https://www.linkedin.com/in/alisadikinma" style="color:#8A8F98;text-decoration:underline;">LinkedIn</a>
                        &nbsp;&middot;&nbsp;
                        <a href="https://alisadikinma.com" style="color:#8A8F98;text-decoration:underline;">Portfolio</a>
                        <br><br>
                        <span style="color:#5A5F68;">You're receiving this because you subscribed at alisadikinma.com.</span>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>
