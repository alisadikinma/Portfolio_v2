@php
    $location = $basics['location'] ?? collect([$basics['city'] ?? null, $basics['country'] ?? null])
        ->filter()
        ->implode(', ');

    $contactBits = collect([
        $basics['email'] ?? null,
        $basics['phone'] ?? null,
    ])->filter()->all();

    $profileBits = collect($basics['profiles'] ?? [])
        ->map(fn ($p) => preg_replace('#^https?://(www\.)?#', '', (string) ($p['url'] ?? '')))
        ->filter()
        ->all();

    $allBits = array_merge($contactBits, $profileBits);
@endphp
# {!! $basics['name'] !!}
**{!! $basics['title'] !!}**@if($location !== '') · {!! $location !!}@endif

@if(!empty($allBits))
{!! implode(' · ', $allBits) !!}

@endif
@if(!empty($basics['hero_tagline']))
> {!! $basics['hero_tagline'] !!}

@endif
## Summary

{!! $basics['summary'] !!}

@if(!$compact && !empty($basics['mission']))
## Mission

{!! $basics['mission'] !!}

@endif
@if(!$compact && !empty($basics['approach']))
## Approach

{!! $basics['approach'] !!}

@endif
@if(!empty($basics['availability_note']))
## Availability

{!! $basics['availability_note'] !!}

@endif
@if(!empty($skills_list))
## Core Skills

@foreach($skills_list as $skill)
- {!! $skill !!}
@endforeach

@endif
## Skills Matrix

@foreach($skill_domains as $domain)
### {!! $domain['label'] !!} (~{{ $domain['years'] }} yrs · {{ $domain['count'] }} projects)
@foreach($domain['bullets'] as $bullet)
- {!! $bullet !!}
@endforeach

@endforeach
@if(!empty($experience))
## Experience

@foreach($experience as $e)
@php
    $expLine = '### ' . $e['title'];
    if (!empty($e['company'])) {
        $expLine .= ' · ' . $e['company'];
    }
    if (!empty($e['period'])) {
        $expLine .= ' (' . $e['period'] . ')';
    }
    $metaParts = collect([
        $e['location'] ?? null,
        $e['company_url'] ?? null,
    ])->filter()->implode(' · ');
@endphp
{!! $expLine !!}
@if($metaParts !== '')
{!! $metaParts !!}
@endif
@if(!empty($e['description']))

{!! $e['description'] !!}
@endif

@endforeach
@endif
@if(!empty($education))
## Education

@foreach($education as $ed)
@php
    $eduLine = '- **' . $ed['degree'] . '**';
    if (!empty($ed['institution'])) {
        $eduLine .= ' · ' . $ed['institution'];
    }
    if (!empty($ed['period'])) {
        $eduLine .= ' (' . $ed['period'] . ')';
    }
    if (!empty($ed['description'])) {
        $eduLine .= ' — ' . $ed['description'];
    }
@endphp
{!! $eduLine !!}
@endforeach

@endif
## Selected Projects ({{ count($projects) }})

@foreach($projects as $i => $p)
@php
    $titleLine = ($i + 1) . '. ' . $p['title'];
    if (!empty($p['role'])) {
        $titleLine .= ' — ' . $p['role'];
    }
    if (!empty($p['year_range'])) {
        $titleLine .= ' (' . $p['year_range'] . ')';
    }

    $metaLine = collect([
        !empty($p['industry']) ? 'Industry: ' . $p['industry'] : null,
        !empty($p['tech_stack']) ? 'Stack: ' . $p['tech_stack'] : null,
    ])->filter()->implode(' · ');
@endphp
### {!! $titleLine !!}
@if($metaLine !== '')
{!! $metaLine !!}
@endif
@if(!$compact && !empty($p['url']))
URL: {{ $p['url'] }}
@endif
@if(!$compact && !empty($p['description']))

{!! $p['description'] !!}
@endif
@if(!$compact && !empty($p['narrative']))

@foreach($p['narrative'] as $beat)
- **{{ $beat['label'] }}:** {!! $beat['text'] !!}
@endforeach
@endif
@if(!$compact && !empty($p['metrics']))
Metrics: {!! $p['metrics'] !!}
@endif
@if(!empty($p['relevance']))
Relevance: {{ $p['relevance'] }}
@endif

@endforeach
@if(!empty($awards))
## Awards & Recognition

@foreach($awards as $a)
@php
    $awardLine = '- ';
    if (!empty($a['year'])) {
        $awardLine .= '**' . $a['year'] . '** — ';
    }
    $awardLine .= $a['title'];
    if (!empty($a['organization'])) {
        $awardLine .= ' · ' . $a['organization'];
    }
    if (!empty($a['description'])) {
        $awardLine .= ' — ' . $a['description'];
    }
@endphp
{!! $awardLine !!}
@endforeach

@endif
@if(!empty($thought_leadership))
## Thought Leadership

@foreach($thought_leadership as $t)
@php
    $thoughtTail = collect([
        $t['date'] ?? null,
        $t['category'] ?? null,
        $t['excerpt'] ?? null,
    ])->filter()->implode(' · ');
@endphp
- [{!! $t['title'] !!}]({{ $t['url'] }}) · {!! $thoughtTail !!}
@endforeach

@endif
---
Generated {{ $generated_at }} · {{ $self_url }}
