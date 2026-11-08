@extends('layouts.app')
@section('title', 'Frais et barèmes')
@section('content')
@include('partials.head', ['title' => 'Frais, barèmes et tranches', 'sub' => 'Aucun montant n’est codé dans l’application : chaque barème est saisi, versionné et approuvé.',
    'actions' => (can_do('fees.manage') ? '<a class="btn btn-light" href="'.route('fees.categories.create').'"><i class="bi bi-plus"></i> Nouveau frais</a><a class="btn btn-primary" href="'.route('fees.schedules.create').'"><i class="bi bi-plus-lg"></i> Nouveau barème</a>' : '')])
<div class="card mb-3"><div class="card-header">Catégories de frais</div>
    <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Code</th><th>Libellé</th><th>Nature</th><th>Établissement</th><th>Application</th><th>Comptes autorisés</th><th>Validité</th><th></th></tr></thead><tbody>
    @foreach($categories as $c)
        <tr class="{{ $c->is_active ? '' : 'text-muted' }}"><td data-l="Code" class="fw-600">{{ $c->code }}</td><td data-l="Libellé">{{ $c->label }}</td><td data-l="Nature">{{ $c->nature === 'mandatory' ? 'Obligatoire' : 'Conditionnel' }}</td>
            <td data-l="Établissement" class="small">{{ ['enrollment' => 'À l’inscription', 'exam_session' => 'Session S'.$c->session_semester.' '.($c->session_type === 'resit' ? 'rattrapage' : 'ordinaire'), 'manual' => 'Sur décision'][$c->trigger] }}</td>
            <td data-l="Application" class="small">{{ $c->applicable_statuses ? collect($c->applicable_statuses)->map(fn ($s) => status_label('enrollment_status', $s))->join(', ') : 'Tous statuts' }}{{ $c->final_promotions_only ? ' · promotions finales' : '' }}</td>
            <td data-l="Comptes" class="small">{{ $c->bankAccounts->map(fn ($a) => $a->bank->code.' '.$a->account_number)->join(', ') ?: '⚠ aucun' }}</td>
            <td data-l="Validité" class="small">{{ $c->valid_from?->format('d/m/Y') ?? '…' }} → {{ $c->valid_until?->format('d/m/Y') ?? '…' }}</td>
            <td>@if(can_do('fees.manage'))<a class="btn btn-sm btn-light" href="{{ route('fees.categories.edit', $c) }}" aria-label="Modifier {{ $c->label }}"><i class="bi bi-pencil"></i></a>@endif</td></tr>
    @endforeach
    </tbody></table></div></div>

<div class="card mb-3"><div class="card-header">Barèmes de l’année
    <form method="get" class="d-flex gap-2"><label class="visually-hidden" for="fy">Année</label><select id="fy" name="year" class="form-select form-select-sm" onchange="this.form.submit()">@foreach($years as $y)<option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->code }}</option>@endforeach</select></form></div>
    <div class="table-responsive"><table class="table table-sm table-hover table-stack mb-0"><thead><tr><th>Frais</th><th>Critères</th><th>Version</th><th class="num">Montant</th><th>Tranches</th><th>Effet</th><th>Statut</th><th>Préparé / approuvé</th></tr></thead><tbody>
    @forelse($schedules as $s)
        <tr><td data-l="Frais"><a href="{{ route('fees.schedules.show', $s) }}">{{ $s->category->label }}</a></td><td data-l="Critères" class="small">{{ $s->criteriaLabel() }}</td><td data-l="Version">v{{ $s->version }}</td>
            <td data-l="Montant" class="num">{{ money($s->amount, $s->currency) }}</td><td data-l="Tranches" class="small">{{ $s->installments->map(fn ($i) => $i->label.' '.money($i->amount).' au '.$i->due_date->format('d/m'))->join(' · ') }}</td>
            <td data-l="Effet">{{ $s->effective_from->format('d/m/Y') }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('schedule', $s->status) !!}</td><td data-l="Par" class="small">{{ $s->creator->name }}{{ $s->approver ? ' / '.$s->approver->name : '' }}</td></tr>
    @empty<tr><td colspan="8">@include('partials.empty', ['icon' => 'bi-tags', 'text' => 'Aucun barème pour cette année.'])</td></tr>@endforelse
    </tbody></table></div></div>

@if(can_do('charges.generate') && $year && $year->status === 'open')
<form method="post" action="{{ route('charges.generate.bulk') }}" class="card" data-cascade data-confirm="Générer les créances manquantes ? Les créances existantes ne sont jamais dupliquées.">@csrf
    <div class="card-header">Générer les créances à partir des inscriptions et des barèmes actifs</div>
    <div class="card-body filters"><input type="hidden" name="year" value="{{ $year->id }}">
        <div><label class="form-label" for="gf">Faculté</label><select id="gf" name="faculty" class="form-select" data-faculty><option value="">Toutes</option>@foreach($faculties as $f)<option value="{{ $f->id }}">{{ $f->code }} — {{ $f->name }}</option>@endforeach</select></div>
        <div><label class="form-label" for="gp">Promotion</label><select id="gp" name="promotion" class="form-select"><option value="">Toutes</option>@foreach($promotions as $p)<option value="{{ $p->id }}" data-fac="{{ $p->program->department->faculty_id }}">{{ $p->code }}</option>@endforeach</select></div>
        <div class="acts"><button class="btn btn-accent"><i class="bi bi-lightning"></i> Générer</button></div>
    </div></form>
@endif
@endsection
