@extends('layouts.app')
@section('title', $year->code)
@section('content')
@include('partials.head', ['title' => 'Année académique '.$year->code, 'crumbs' => ['Années' => route('years.index')], 'sub' => \App\Support\Labels::badge('year', $year->status).($year->is_current ? ' · année courante' : '')])
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">Actions</div><div class="card-body d-flex flex-wrap gap-2">
            @if($year->status === 'planned' && can_do('years.manage'))<form method="post" action="{{ route('years.open', $year) }}">@csrf<button class="btn btn-accent">Ouvrir l’année</button></form>@endif
            @if(!$year->is_current && can_do('years.manage'))<form method="post" action="{{ route('years.current', $year) }}">@csrf<button class="btn btn-light">Définir comme courante</button></form>@endif
        </div></div>
        <div class="card mb-3"><div class="card-header">Sessions d’examens</div><ul class="list-group list-group-flush">
            @forelse($year->sessions as $s)<li class="list-group-item"><a href="{{ route('exams.show', $s) }}">{{ $s->name }}</a> — S{{ $s->semester }} {{ $s->type === 'resit' ? 'rattrapage' : 'ordinaire' }}</li>@empty<li class="list-group-item text-muted-2 small">Aucune.</li>@endforelse</ul>
            @if(can_do('years.manage') && $year->status !== 'closed')
            <form method="post" action="{{ route('years.sessions.store', $year) }}" class="card-body row g-2 border-top">@csrf
                <div class="col-md-6"><label class="form-label" for="sn">Nom</label><input id="sn" name="name" class="form-control form-control-sm" required placeholder="Examens du premier semestre"></div>
                <div class="col-md-3"><label class="form-label" for="ss">Semestre</label><select id="ss" name="semester" class="form-select form-select-sm"><option>1</option><option>2</option></select></div>
                <div class="col-md-3"><label class="form-label" for="st">Type</label><select id="st" name="type" class="form-select form-select-sm"><option value="ordinary">Ordinaire</option><option value="resit">Rattrapage</option></select></div>
                <div class="col-md-4"><label class="form-label" for="sd">Début</label><input id="sd" type="date" name="starts_on" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label" for="se">Fin</label><input id="se" type="date" name="ends_on" class="form-control form-control-sm"></div>
                <div class="col-md-4 d-flex align-items-end"><button class="btn btn-light btn-sm w-100">Ajouter</button></div></form>
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        @if($check)
            <div class="card"><div class="card-header">Clôture contrôlée</div><div class="card-body">
                <h2 class="h6 fw-bold">Éléments bloquants</h2>
                <ul class="list-unstyled">@foreach($check['blocking'] as $l => $n)<li><i class="bi {{ $n ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success' }}"></i> {{ $l }} : <strong>{{ $n }}</strong></li>@endforeach
                    <li><i class="bi {{ $check['next'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i> Année suivante liée : {{ $check['next']?->code ?? 'à créer' }}</li></ul>
                <h2 class="h6 fw-bold">Points de vigilance</h2>
                <ul class="list-unstyled">@foreach($check['warnings'] as $l => $n)<li><i class="bi {{ $n ? 'bi-exclamation-triangle-fill text-warning' : 'bi-check-circle-fill text-success' }}"></i> {{ $l }} : <strong>{{ $n }}</strong></li>@endforeach</ul>
                <p class="small">Reports prévus vers {{ $check['next']?->code ?? '…' }} : <strong>{{ $check['arrears_count'] }}</strong> arriéré(s) (ajustement « report » sur la créance d’origine + créance reportée liée) et <strong>{{ $check['credits_count'] }}</strong> crédit(s) (paiement de report lié au paiement d’origine). Aucun report n’est dupliqué.</p>
                @if(can_do('years.close'))
                <form method="post" action="{{ route('years.close', $year) }}" data-confirm="Clôturer {{ $year->code }} ? Les écritures ordinaires seront bloquées (interface, API, synchronisation).">@csrf
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="acknowledge" value="1" id="ack"><label class="form-check-label small" for="ack">J’ai examiné les points de vigilance</label></div>
                    <label class="form-label" for="cn">Commentaire de clôture *</label><textarea id="cn" name="note" class="form-control mb-2" rows="2" required></textarea>
                    <button class="btn btn-primary" @disabled(!$check['can_close'])>Clôturer l’année</button></form>
                @endif
            </div></div>
        @elseif($year->status === 'closed')
            <div class="card"><div class="card-header">Année clôturée</div><div class="card-body">
                <p class="small">{{ $year->closing_report }}</p>
                @if(can_do('years.reopen'))<form method="post" action="{{ route('years.reopen', $year) }}" data-confirm="Rouvrir exceptionnellement cette année ?">@csrf<label class="form-label" for="rr">Motif détaillé et autorité de la réouverture *</label><textarea id="rr" name="reason" class="form-control mb-2" rows="2" required minlength="10"></textarea><button class="btn btn-outline-danger btn-sm">Réouverture exceptionnelle</button></form>@endif
            </div></div>
        @endif
    </div>
</div>
@endsection
