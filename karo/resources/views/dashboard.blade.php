@extends('layouts.app')
@section('title', 'Tableau de bord')
@section('content')
@include('partials.head', ['title' => 'Tableau de bord', 'sub' => 'Année '.e($filters->year?->code ?? '—').' · données du serveur de référence au '.now()->format('d/m/Y à H:i').' · montants présentés par devise, jamais additionnés entre devises'])
@include('partials.tiles')
<div class="card mb-3"><div class="card-body">@include('partials.filters', ['show' => ['year', 'faculty', 'program', 'promotion', 'fee', 'account', 'currency', 'period']])</div></div>

@if($toReview || $syncConflicts || $anomalies || $breached)
<div class="d-flex flex-wrap gap-2 mb-3">
    @if($anomalies)<a class="btn btn-sm btn-outline-danger" href="{{ route('payments.control') }}"><i class="bi bi-exclamation-triangle"></i> {{ $anomalies }} bordereau(x) avec anomalie en attente</a>@endif
    @if($toReview)<a class="btn btn-sm btn-outline-warning" href="{{ route('bank.lines', ['review' => 'pending']) }}"><i class="bi bi-eye"></i> {{ $toReview }} ligne(s) de relevé à vérifier</a>@endif
    @if($syncConflicts)<a class="btn btn-sm btn-outline-danger" href="{{ route('sync.index') }}"><i class="bi bi-cloud-slash"></i> {{ $syncConflicts }} conflit(s) de synchronisation</a>@endif
    @if($breached)<a class="btn btn-sm btn-outline-danger" href="{{ route('contracts.index', ['breached' => 1]) }}"><i class="bi bi-file-earmark-x"></i> {{ $breached }} contrat(s) non respecté(s)</a>@endif
</div>
@endif

@forelse($metrics as $cur => $m)
    <h2 class="h6 text-uppercase text-muted-2 fw-bold mt-2 mb-2" style="letter-spacing:.06em">Devise : {{ $cur }}</h2>
    <div class="row g-3 mb-4">
        @foreach([['expected', ''], ['exigible', ''], ['allocated', 'accent'], ['balance_due', 'alert-k'], ['declared', ''], ['validated', ''], ['confirmed', 'accent'], ['unallocated', '']] as [$k, $cls])
            <div class="col-6 col-md-4 col-xl-3"><div class="card kpi {{ $cls }}">
                <div class="l">{{ $definitions[$k][0] }} <i class="bi bi-info-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ $definitions[$k][1] }} Source : {{ $definitions[$k][2] }}."></i></div>
                <div class="v">{{ money($m[$k], $cur) }}</div>
            </div></div>
        @endforeach
        <div class="col-6 col-md-4 col-xl-3"><div class="card kpi accent"><div class="l">{{ $definitions['recovery_rate'][0] }} <i class="bi bi-info-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ $definitions['recovery_rate'][1] }}"></i></div>
            <div class="v">{{ str_replace('.', ',', $m['recovery_rate']) }} %</div>
            <div class="progress mt-2" style="height:6px" role="progressbar" aria-valuenow="{{ (float) $m['recovery_rate'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Taux de recouvrement"><div class="progress-bar" style="width:{{ min(100, (float) $m['recovery_rate']) }}%;background:var(--k-blue)"></div></div></div></div>
        <div class="col-6 col-md-4 col-xl-3"><div class="card kpi alert-k"><div class="l">{{ $definitions['overdue'][0] }} <i class="bi bi-info-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ $definitions['overdue'][1] }}"></i></div><div class="v">{{ money($m['overdue'], $cur) }}</div><div class="h">{{ $m['overdue_count'] }} créance(s)</div></div></div>
        <div class="col-6 col-md-4 col-xl-3"><div class="card kpi warn-k"><div class="l">{{ $definitions['pending'][0] }}</div><div class="v">{{ money($m['pending'], $cur) }}</div><div class="h">{{ $m['pending_count'] }} bordereau(x)</div></div></div>
        <div class="col-6 col-md-4 col-xl-3"><div class="card kpi warn-k"><div class="l">{{ $definitions['unmatched'][0] }}</div><div class="v">{{ money($m['unmatched'], $cur) }}</div><div class="h">{{ $m['unmatched_count'] }} ligne(s)</div></div></div>
    </div>
@empty
    <div class="card">@include('partials.empty', ['icon' => 'bi-bar-chart', 'text' => 'Aucune donnée financière pour ces filtres.'])</div>
@endforelse

<div class="card"><div class="card-header">Derniers bordereaux saisis @if(can_do('payments.view'))<a href="{{ route('payments.index') }}" class="small fw-normal">Tous les paiements</a>@endif</div>
    <div class="table-responsive"><table class="table table-hover table-stack">
        <thead><tr><th>N°</th><th>Étudiant</th><th>Date bancaire</th><th>Compte</th><th class="num">Montant</th><th>Statut</th><th>Banque</th></tr></thead>
        <tbody>
        @forelse($latest as $p)
            <tr><td data-l="N°"><a href="{{ route('payments.show', $p) }}">{{ $p->number }}</a></td><td data-l="Étudiant">{{ $p->student->matricule }} — {{ $p->student->fullName() }}</td><td data-l="Date">{{ $p->deposit_date?->format('d/m/Y') }}</td>
                <td data-l="Compte">{{ $p->bankAccount?->bank->code }} {{ $p->bankAccount?->account_number }}</td><td data-l="Montant" class="num">{{ money($p->amount, $p->currency) }}</td>
                <td data-l="Statut">{!! \App\Support\Labels::badge('payment', $p->status) !!}</td><td data-l="Banque">{!! \App\Support\Labels::badge('bank', $p->bank_status) !!}</td></tr>
        @empty
            <tr><td colspan="7">@include('partials.empty', ['text' => 'Aucun bordereau.'])</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle"></i> Les encaissements confirmés ne sont pas des produits comptables : l’intégration à une comptabilité générale relève d’une spécification distincte.</p>
@endsection
