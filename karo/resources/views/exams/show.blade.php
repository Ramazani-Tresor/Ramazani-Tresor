@extends('layouts.app')
@section('title', $session->name)
@section('content')
@include('partials.head', ['title' => 'Contrôle financier — '.$session->name, 'crumbs' => ['Examens' => route('exams.index', ['year' => $session->academic_year_id])],
    'sub' => e($session->academicYear->code).' · conformité FINANCIÈRE uniquement (ne vaut pas admissibilité académique)',
    'actions' => can_do('reports.export') ? '<a class="btn btn-light" href="'.route('exams.export', [$session, 'xlsx']).'?'.http_build_query(request()->only('faculty', 'promotion', 'status')).'"><i class="bi bi-file-earmark-excel"></i> Excel</a><a class="btn btn-light" href="'.route('exams.export', [$session, 'pdf']).'?'.http_build_query(request()->only('faculty', 'promotion', 'status')).'"><i class="bi bi-file-earmark-pdf"></i> PDF</a>' : ''])
<div class="row g-2 mb-3">@foreach(['cleared' => 'accent', 'partial' => 'warn-k', 'due' => 'alert-k', 'waiver' => ''] as $k => $c)<div class="col-6 col-md-3"><a class="card kpi {{ $c }} text-decoration-none" href="{{ request()->fullUrlWithQuery(['status' => $k]) }}"><div class="l">{{ status_label('clearance', $k) }}</div><div class="v">{{ $counts[$k] ?? 0 }}</div></a></div>@endforeach</div>
<div class="row g-3">
    <div class="col-xl-8"><div class="card">
        <div class="card-body border-bottom">@include('partials.filters', ['show' => ['faculty', 'promotion', 'status'], 'statuses' => \App\Support\Labels::STATUSES['clearance']])</div>
        <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Matricule</th><th>Nom</th><th>Promotion</th><th>Détail (réglé / requis)</th><th>Statut</th></tr></thead><tbody>
        @forelse($rows as $r)
            @php $e = $r['enrollment']; @endphp
            <tr><td data-l="Matricule"><a href="{{ route('students.show', $e->student) }}">{{ $e->student->matricule }}</a></td><td data-l="Nom">{{ $e->student->fullName() }}</td><td data-l="Promotion">{{ $e->promotion->code }}</td>
                <td data-l="Détail" class="small">@forelse($r['details'] as $cur => $d){{ money($d['settled'], $cur) }} / {{ money($d['required'], $cur) }} ({{ str_replace('.', ',', $d['ratio']) }} %)<br>@empty Aucun frais requis @endforelse</td>
                <td data-l="Statut"><span class="badge-st st-{{ ['cleared' => 'ok', 'partial' => 'warn', 'due' => 'danger', 'waiver' => 'info'][$r['status']] }}">{{ status_label('clearance', $r['status']) }}</span>@if($r['waiver'])<div class="small">{{ $r['waiver']->authority }}, jusqu’au {{ $r['waiver']->valid_until->format('d/m/Y') }}</div>@endif</td></tr>
        @empty<tr><td colspan="5">@include('partials.empty', ['text' => 'Aucun étudiant pour ces filtres.'])</td></tr>@endforelse
        </tbody></table></div></div></div>
    <div class="col-xl-4">
        @if(can_do('exams.manage'))
        <form method="post" action="{{ route('exams.conditions', $session) }}" class="card mb-3">@csrf @method('PUT')
            <div class="card-header">Conditions de la session</div>
            <div class="card-body">
                <fieldset class="mb-2"><legend class="form-label">Frais pris en compte</legend>@foreach($categories as $c)<div class="form-check"><input class="form-check-input" type="checkbox" name="fee_category_ids[]" value="{{ $c->id }}" id="fc{{ $c->id }}" @checked(in_array($c->id, $session->fee_category_ids ?? []))><label class="form-check-label small" for="fc{{ $c->id }}">{{ $c->label }}</label></div>@endforeach</fieldset>
                <div class="mb-2"><label class="form-label" for="th">Seuil minimal réglé (%)</label><input id="th" name="threshold_percent" class="form-control" value="{{ $session->threshold_percent }}"></div>
                <div class="mb-2"><label class="form-label" for="pd">Échéance prise en compte (tranches échues à cette date)</label><input id="pd" type="date" name="payment_deadline" class="form-control" value="{{ $session->payment_deadline?->toDateString() }}"></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="require_bank_confirmation" value="1" id="rbc" @checked($session->require_bank_confirmation)><label class="form-check-label small" for="rbc">Exiger la confirmation bancaire (sinon : validation interne suffit)</label></div>
            </div><div class="card-footer bg-white"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form>
        @endif
        @if(can_do('charges.generate') && $session->academicYear->status === 'open')
        <form method="post" action="{{ route('exams.charges', $session) }}" class="card mb-3" data-confirm="Générer les créances d’inscription à cette session ?">@csrf
            <div class="card-header">Créances d’inscription à la session</div>
            <div class="card-body"><label class="form-label" for="mt">{{ $session->type === 'resit' ? 'Matricules des étudiants au rattrapage *' : 'Matricules (vide = tous les inscrits)' }}</label><textarea id="mt" name="matricules" class="form-control" rows="3" placeholder="Un matricule par ligne"></textarea></div>
            <div class="card-footer bg-white"><button class="btn btn-light btn-sm">Générer</button></div></form>
        @endif
        @if(can_do('exams.waive'))
        <form method="post" action="{{ route('exams.waivers.store', $session) }}" class="card mb-3">@csrf
            <div class="card-header">Dérogation exceptionnelle</div>
            <div class="card-body row g-2">
                <div class="col-12"><label class="form-label" for="wm">Matricule</label><input id="wm" name="matricule" class="form-control" required></div>
                <div class="col-12"><label class="form-label" for="wr">Motif</label><input id="wr" name="reason" class="form-control" required maxlength="255"></div>
                <div class="col-12"><label class="form-label" for="wa">Autorité</label><input id="wa" name="authority" class="form-control" required maxlength="150" placeholder="Ex. Recteur, décision n°…"></div>
                <div class="col-6"><label class="form-label" for="wg">Date</label><input id="wg" type="date" name="granted_on" class="form-control" value="{{ now()->toDateString() }}" required></div>
                <div class="col-6"><label class="form-label" for="wv">Valable jusqu’au</label><input id="wv" type="date" name="valid_until" class="form-control" value="{{ $session->ends_on?->toDateString() }}" required></div>
            </div><div class="card-footer bg-white"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form>
        @endif
        <div class="card"><div class="card-header">Dérogations</div><ul class="list-group list-group-flush small">
            @forelse($session->waivers as $w)<li class="list-group-item">{{ $w->student->matricule }} — {{ $w->reason }} — {{ $w->authority }}, {{ $w->granted_on->format('d/m/Y') }} → {{ $w->valid_until->format('d/m/Y') }} — {{ $w->status === 'active' ? 'active' : 'révoquée' }}
                @if($w->status === 'active' && can_do('exams.waive'))<form method="post" action="{{ route('exams.waivers.revoke', $w) }}" class="d-inline">@csrf<button class="btn btn-link btn-sm p-0">révoquer</button></form>@endif</li>
            @empty<li class="list-group-item text-muted-2">Aucune.</li>@endforelse</ul></div>
    </div>
</div>
@endsection
