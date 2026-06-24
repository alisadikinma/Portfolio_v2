{{-- Server-rendered crawlable project case-study body for project detail pages.
     Injected into <div id="app"> as pre-mount markup; Vue overwrites it on
     hydration so real users see the SPA and crawlers/LLMs read real content.
     $content is authored, trusted HTML (project_translations.content /
     projects.content). $meta is a label => value map of present case-study
     fields only. --}}
<article>
    <header>
        <h1>{{ $title }}</h1>
        @if(!empty($category))
            <p>Category: {{ $category }}</p>
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

    @if(!empty($meta))
        <dl>
            @foreach($meta as $label => $value)
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            @endforeach
        </dl>
    @endif

    @if(!empty($content))
        <div>{!! $content !!}</div>
    @endif
</article>
