@extends('layouts.app')
@section('title', 'Congés et situations')
@php $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; @endphp
@section('content')
@include('partials.head', ['title' => 'Congés et situations administratives', 'sub' => 'Demandes à approuver, situations en cours et échéances. Les conséquences sur les affectations et les primes suivent les règles paramétrées par type.'])
@foreach([['À approuver', $pending, 'bi-hourglass-split'], ['Échéance sous '.config('grh.alert_days_situation').' jours', $expiring, 'bi-alarm'], ['En cours', $ongoing, 'bi-play-circle'], ['Clôturées ou rejetées (récentes)', $closed, 'bi-archive']] as [$title, $list, $icon])
<div class="card mb-3"><div class="card-header"><span><i class="bi {{ $icon }}"></i> {{ $title }} ({{ $list->count() }})</span></div>
@if($list->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Agent</th><th>Type</th><th>Période</th><th>Durée</th><th>Motif</th><th>Primes</th><th>Statut</th><th></th></tr></thead><tbody>
@foreach($list as $s)<tr>
    <td data-l="Agent"><a href="{{ route('employees.show', $s->employee) }}#situations">{{ $s->employee->fullName() }}</a><br><span class="small text-muted-2">{{ $s->employee->activeAssignment?->orgUnit?->name }}</span></td>
    <td data-l="Type">{{ $s->type->name }}</td><td data-l="Période" class="nw">{{ $d($s->start_date) }} → {{ $s->actual_end_date ? $d($s->actual_end_date) : $d($s->planned_end_date) }}</td><td data-l="Durée">{{ $s->durationDays() ? $s->durationDays().' j' : '—' }}</td>
    <td data-l="Motif">{{ \Illuminate\Support\Str::limit($s->reason, 60) }}</td><td data-l="Primes">{{ $s->type->prime_percent }} %</td><td data-l="Statut">{!! \App\Support\Labels::badge('situation', $s->status) !!}</td>
    <td>@include('situations._actions', ['s' => $s])</td>
</tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucune.</div> @endif
</div>
@endforeach
@endsection
