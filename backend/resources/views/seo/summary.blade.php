{{-- Server-rendered crawlable summary for homepage / blog list / category.
     Wrapped in <noscript> and injected into <div id="app">; Vue overwrites
     #app on mount so JS users never see it, while non-JS crawlers + LLMs get
     real text + post links. --}}
<noscript>
    <section>
        <h1>{{ $heading }}</h1>
        @if(!empty($intro))
            <p>{{ $intro }}</p>
        @endif
        @if(!empty($items))
            <ul>
                @foreach($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                        @if(!empty($item['excerpt'])) — {{ $item['excerpt'] }}@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</noscript>
