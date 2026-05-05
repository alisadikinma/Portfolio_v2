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
