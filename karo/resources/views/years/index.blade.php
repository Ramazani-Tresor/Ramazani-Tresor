@extends('layouts.app')
@section('title', 'Années académiques')
@section('content')
@include('partials.head', ['title' => 'Années académiques et clôtures'])
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-hover table-stack mb-0"><thead><tr><th>Année</th><th>Période</th><th>Précédente</th><th>Sessions</th><th>Statut</th></tr></thead><tbody>
        @forelse($years as $y)<tr><td data-l="Année"><a href="{{ route('years.show', $y) }}" class="fw-600">{{ $y->code }}</a>{{ $y->is_current ? ' ★' : '' }}</td><td data-l="Période">{{ $y->starts_on->format('d/m/Y') }} → {{ $y->ends_on->format('d/m/Y') }}</td><td data-l="Précédente">{{ $y->previous?->code ?? '—' }}</td><td data-l="Sessions">{{ $y->sessions_count }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('year', $y->status) !!}{{ $y->closed_at ? ' le '.$y->closed_at->format('d/m/Y') : '' }}</td></tr>
        @empty<tr><td colspan="5">@include('partials.empty')</td></tr>@endforelse</tbody></table></div></div></div>
    @if(can_do('years.manage'))
    <div class="col-lg-4"><form method="post" action="{{ route('years.store') }}" class="card">@csrf<div class="card-header">Nouvelle année</div><div class="card-body d-grid gap-2">
        <div><label class="form-label" for="yc">Code</label><input id="yc" name="code" class="form-control" placeholder="2026-2027" required></div>
        <div><label class="form-label" for="ys">Début</label><input id="ys" type="date" name="starts_on" class="form-control" required></div>
        <div><label class="form-label" for="ye">Fin</label><input id="ye" type="date" name="ends_on" class="form-control" required></div>
        <div><label class="form-label" for="yp">Année précédente (reçoit… / reporte vers celle-ci)</label><select id="yp" name="previous_year_id" class="form-select"><option value="">—</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
    </div><div class="card-footer bg-white"><button class="btn btn-primary">Créer</button></div></form></div>
    @endif
</div>
@endsection
