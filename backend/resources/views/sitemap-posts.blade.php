<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if(!empty($url['alternates']))
@foreach($url['alternates'] as $lang => $slug)
        <xhtml:link rel="alternate" hreflang="{{ $lang }}" href="{{ $url['frontend_base'] }}/{{ $lang }}/blog/{{ $slug }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['x_default'] }}"/>
@endif
        <lastmod>{{ $url['lastmod'] }}</lastmod>
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
    </url>
@endforeach
</urlset>
