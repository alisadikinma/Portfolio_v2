@php
    $location = collect([$basics['city'] ?? null, $basics['country'] ?? null])
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
## Summary

{!! $basics['summary'] !!}

## Skills Matrix

@foreach($skill_domains as $domain)
### {!! $domain['label'] !!} (~{{ $domain['years'] }} yrs · {{ $domain['count'] }} projects)
@foreach($domain['bullets'] as $bullet)
- {!! $bullet !!}
@endforeach

@endforeach
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
@if(!$compact && !empty($p['problem']))
Problem: {!! $p['problem'] !!}
@endif
@if(!$compact && !empty($p['outcome']))
Outcome: {!! $p['outcome'] !!}
@endif
@if(!empty($p['relevance']))
Relevance: {{ $p['relevance'] }}
@endif

@endforeach
