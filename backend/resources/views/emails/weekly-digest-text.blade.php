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
@if(!empty($featuredProjects) && $featuredProjects->count() > 0)
---

FEATURED PROJECTS

@foreach($featuredProjects as $fp)
@php
$fpExcerpt = $fp->impact_statement ?? \Illuminate\Support\Str::limit(strip_tags($fp->description ?? ''), 160);
@endphp
{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}/{{ str_pad((string) $loop->count, 2, '0', STR_PAD_LEFT) }} · {{ $fp->title }}@if(!empty($fp->category)) ({{ $fp->category }})@endif
@if($fpExcerpt){{ \Illuminate\Support\Str::limit($fpExcerpt, 160) }}
@endif
View case study: https://alisadikinma.com/projects/{{ $fp->slug }}?utm_source=newsletter&utm_medium=email&utm_campaign={{ $campaign }}

@endforeach
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
