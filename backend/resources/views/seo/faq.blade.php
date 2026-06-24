{{-- Server-rendered crawlable FAQ for /faq (GEO Pillar 2). Injected into
     <div id="app">; Vue overwrites #app on mount so JS users never see it,
     while crawlers + LLMs get a real <dl> of standalone Q&A. NOT wrapped in
     <noscript> — JS-executing crawlers (Googlebot) must read this markup before
     Vue replaces #app, mirroring seo/article + seo/project. Answers are plain
     text — escaped with {{ }}. Source: config/faq.php (single source). --}}
<section>
    <h1>Frequently Asked Questions — Ali Sadikin Ma</h1>
    @if(!empty($items))
        <dl>
            @foreach($items as $item)
                <dt>{{ $item['question'] }}</dt>
                <dd>{{ $item['answer'] }}</dd>
            @endforeach
        </dl>
    @endif
</section>
