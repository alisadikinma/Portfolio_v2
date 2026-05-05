Hi {{ $subscriber->name ?? 'there' }},

@if($posts->count() === 0)
It's been a quiet week on the blog — no new essays just yet. Check back next Friday.
@else
Here's what landed on the blog this week — {{ $posts->count() }} {{ $posts->count() === 1 ? 'essay' : 'essays' }} you might've missed.

@foreach($posts as $post)
@php
$translation = $post->translations->first();
$title = $translation?->title ?? $post->slug;
$url = 'https://alisadikinma.com/blog/' . $post->slug . '?utm_source=newsletter&utm_medium=email&utm_campaign=' . $campaign;
@endphp
{{ '· ' . $title }}
  {{ $url }}

@endforeach
@endif
@if(!empty($featuredProject))
---

FEATURED PROJECT

{{ $featuredProject->title }}@if(!empty($featuredProject->category)) ({{ $featuredProject->category }})@endif
@php
$projectExcerpt = $featuredProject->impact_statement ?? \Illuminate\Support\Str::limit(strip_tags($featuredProject->description ?? ''), 160);
@endphp
@if($projectExcerpt){{ \Illuminate\Support\Str::limit($projectExcerpt, 180) }}
@endif
View case study: https://alisadikinma.com/projects/{{ $featuredProject->slug }}?utm_source=newsletter&utm_medium=email&utm_campaign={{ $campaign }}

@endif
---

KONSULTASI AI · 1-on-1

Need an AI expert for your business?
Ali Sadikin adalah AI Generalist Expert — diskusi langsung di WhatsApp tentang bagaimana AI bisa meningkatkan efisiensi operasional perusahaan atau tempat kerja Anda.

💬 Chat di WhatsApp: https://wa.me/6281380163758
+62 813-8016-3758

---

Reply to this email — I read every one.

Ali Sadikin
https://alisadikinma.com

---

Unsubscribe: https://alisadikinma.com/newsletter/unsubscribe?token={{ $subscriber->unsubscribe_token }}
LinkedIn: https://www.linkedin.com/in/alisadikinma
