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
---

Reply to this email — I read every one.

Ali Sadikin
https://alisadikinma.com

---

Unsubscribe: https://alisadikinma.com/newsletter/unsubscribe?token={{ $subscriber->unsubscribe_token }}
LinkedIn: https://www.linkedin.com/in/alisadikinma
