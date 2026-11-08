<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>{{ $r['title'] }}</title>@include('pdf._styles')</head><body>
@include('pdf._letterhead', ['reference' => $r['reference']])
<div class="foot">{{ setting('university_acronym') ?: 'UNILU' }}-GRH · {{ $r['title'] }} · {{ $r['reference'] }}</div>
<h1>{{ $r['title'] }}</h1><div class="sub">{{ $r['period'] }} · {{ count($r['rows']) }} ligne(s)</div>
@if($r['summary'])<table class="summary">@foreach($r['summary'] as $k => $v)<tr><td><b>{{ $k }}</b></td><td>{{ $v }}</td></tr>@endforeach</table>@endif
<table class="data"><thead><tr>@foreach($r['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead><tbody>
@php $prev = null; @endphp
@forelse($r['rows'] as $i => $row)
    @if($r['groups'] && $r['groups'][$i] !== $prev)<tr class="group"><td colspan="{{ count($r['columns']) }}">{{ $r['groups'][$i] }}</td></tr>@php $prev = $r['groups'][$i]; @endphp @endif
    <tr>@foreach($row as $v)<td>{{ $v }}</td>@endforeach</tr>
@empty <tr><td colspan="{{ count($r['columns']) }}">Aucune donnée.</td></tr> @endforelse
</tbody></table>
@include('pdf._signatures')
</body></html>
