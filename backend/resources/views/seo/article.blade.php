{{-- Server-rendered crawlable article body for blog detail pages.
     Injected into <div id="app"> as pre-mount markup; Vue overwrites it on
     hydration so real users see the SPA and crawlers/LLMs read real content.
     $content is authored, trusted HTML (post_translations.content). --}}
<article>
    <header>
        <h1>{{ $title }}</h1>
        <p>
            <span>By {{ $author }}</span>
            @if(!empty($publishedIso))
                &middot; <time datetime="{{ $publishedIso }}">{{ $publishedHuman }}</time>
            @endif
            @if(!empty($modifiedIso) && $modifiedIso !== ($publishedIso ?? null))
                &middot; Updated <time datetime="{{ $modifiedIso }}">{{ $modifiedHuman }}</time>
            @endif
        </p>
        @if(!empty($category))
            <p>Category: <a href="{{ $categoryUrl }}">{{ $category }}</a></p>
        @endif
    </header>

    @if(!empty($image))
        <figure>
            <img src="{{ $image }}" alt="{{ $imageAlt }}" width="1200" height="630">
            <figcaption>{{ $title }}</figcaption>
        </figure>
    @endif

    @if(!empty($summary))
        <p>{{ $summary }}</p>
    @endif

    <div>{!! $content !!}</div>
</article>
