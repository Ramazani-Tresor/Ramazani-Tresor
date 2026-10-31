@extends('layouts.app')
@section('title', $student->matricule)
@section('content')
@php $enr = $year ? $student->enrollments->firstWhere('academic_year_id', $year->id) : null; $open = $year && $year->status === 'open'; @endphp
@include('partials.head', ['title' => $student->fullName(), 'crumbs' => ['Étudiants' => route('students.index')],
    'sub' => '<strong>'.e($student->matricule).'</strong>'.($enr ? ' · '.e($enr->promotion->code).' — '.e($enr->promotion->program->department->faculty->name).' · '.e(status_label('enrollment_status', $enr->status)) : ' · non inscrit pour l’année affichée'),
    'actions' => (can_do('payments.create') && $open ? '<a class="btn btn-accent" href="'.route('payments.entry', ['year' => $year->id, 'student' => $student->id]).'"><i class="bi bi-receipt"></i> Saisir un bordereau</a>' : '').(can_do('students.manage') ? '<a class="btn btn-light" href="'.route('students.edit', $student).'"><i class="bi bi-pencil"></i> Modifier</a>' : '')])

<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <form method="get" class="d-flex gap-2 align-items-end"><div><label class="form-label" for="ys">Année académique</label><select id="ys" name="year" class="form-select" onchange="this.form.submit()">@foreach($years as $y)<option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->code }} — {{ status_label('year', $y->status) }}</option>@endforeach</select></div></form>
    @if($year && $year->status === 'closed')<span class="badge-st st-muted align-self-center">Année clôturée : consultation et éditions uniquement</span>@endif
    <div class="ms-auto d-flex flex-wrap gap-2">
        @if(can_do('charges.generate') && $open && $enr)<form method="post" action="{{ route('charges.generate.student', $student) }}">@csrf<input type="hidden" name="year" value="{{ $year->id }}"><button class="btn btn-light"><i class="bi bi-arrow-repeat"></i> Générer les créances</button></form>@endif
        @if(can_do('documents.issue') && $year)
            <div class="dropdown"><button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-pdf"></i> Éditer un document</button>
                <div class="dropdown-menu dropdown-menu-end p-2" style="min-width:260px">
                    @foreach(['situation' => 'Situation individuelle', 'statement' => 'Relevé financier', 'attestation' => 'Attestation de paiement'] as $t => $l)
                        <form method="post" action="{{ route('documents.issue', $student) }}">@csrf<input type="hidden" name="type" value="{{ $t }}"><input type="hidden" name="year" value="{{ $year->id }}"><button class="dropdown-item">{{ $l }}</button></form>
                    @endforeach
                </div></div>
        @endif
        @if(can_do('contracts.manage') && $open)<a class="btn btn-light" href="{{ route('contracts.create', [$student, 'year' => $year->id]) }}"><i class="bi bi-file-earmark-text"></i> Contrat</a>@endif
    </div>
</div></div>

<ul class="nav nav-tabs" role="tablist">
    @foreach(['situation' => 'Situation financière', 'payments' => 'Paiements ('.$payments->count().')', 'adjustments' => 'Réductions et ajustements ('.$adjustments->count().')', 'contracts' => 'Contrats ('.$contracts->count().')', 'docs' => 'Documents ('.($issued->count() + $student->documents->count()).')', 'info' => 'Identité et inscriptions'] as $k => $l)
        <li class="nav-item" role="presentation"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#t-{{ $k }}" type="button" role="tab">{{ $l }}</button></li>
    @endforeach
</ul>
<div class="tab-content card border-top-0 rounded-top-0 mb-3">
    <div class="tab-pane fade show active card-body" id="t-situation" role="tabpanel">@include('students._situation')</div>

    <div class="tab-pane fade" id="t-payments" role="tabpanel"><div class="table-responsive"><table class="table table-hover table-stack">
        <thead><tr><th>N°</th><th>Type</th><th>Date bancaire</th><th>Référence</th><th class="num">Montant</th><th class="num">Disponible</th><th>Statut</th><th>Banque</th></tr></thead>
        <tbody>@forelse($payments as $p)
            <tr><td data-l="N°"><a href="{{ route('payments.show', $p) }}">{{ $p->number }}</a></td><td data-l="Type">{{ $p->type === 'carry_forward' ? 'Report reçu' : 'Versement' }}</td><td data-l="Date">{{ $p->deposit_date?->format('d/m/Y') }}</td><td data-l="Réf.">{{ $p->bank_reference }}</td>
                <td data-l="Montant" class="num">{{ money($p->amount, $p->currency) }}</td><td data-l="Disponible" class="num">{{ $p->status === 'validated' ? money($p->availableAmount(), $p->currency) : '—' }}</td>
                <td data-l="Statut">{!! \App\Support\Labels::badge('payment', $p->status) !!}</td><td data-l="Banque">{!! \App\Support\Labels::badge('bank', $p->bank_status) !!}</td></tr>
        @empty<tr><td colspan="8">@include('partials.empty', ['text' => 'Aucun paiement pour cette année.'])</td></tr>@endforelse</tbody>
    </table></div></div>

    <div class="tab-pane fade" id="t-adjustments" role="tabpanel"><div class="table-responsive"><table class="table table-stack">
        <thead><tr><th>N°</th><th>Frais</th><th>Type</th><th class="num">Montant</th><th>Motif</th><th>Demandé par</th><th>Décision</th><th>Statut</th></tr></thead>
        <tbody>@forelse($adjustments as $a)
            <tr><td data-l="N°" class="small">{{ $a->number }}</td><td data-l="Frais">{{ $a->charge->category->label }}</td><td data-l="Type">{{ status_label('adjustment_type', $a->type) }}{{ $a->sponsor ? ' ('.$a->sponsor.')' : '' }}</td>
                <td data-l="Montant" class="num">{{ money(ltrim($a->amount, '-'), $a->charge->currency) }}{{ str_starts_with($a->amount, '-') ? ' (hausse)' : '' }}</td><td data-l="Motif" class="small">{{ $a->reason }}</td>
                <td data-l="Demandé par" class="small">{{ $a->requester->name }}</td><td data-l="Décision" class="small">{{ $a->approver?->name }} {{ $a->approved_at?->format('d/m/Y') }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('adjustment', $a->status) !!}</td></tr>
        @empty<tr><td colspan="8">@include('partials.empty', ['text' => 'Aucun ajustement.'])</td></tr>@endforelse</tbody>
    </table></div></div>

    <div class="tab-pane fade" id="t-contracts" role="tabpanel"><div class="table-responsive"><table class="table table-stack">
        <thead><tr><th>Contrat</th><th>Version</th><th class="num">Solde couvert</th><th>Échéances</th><th>Respect</th><th>Statut</th></tr></thead>
        <tbody>@forelse($contracts as $row)
            @php $c = $row['c']; $cp = $row['compliance']; @endphp
            <tr><td data-l="Contrat"><a href="{{ route('contracts.show', $c) }}">{{ $c->number }}</a></td><td data-l="Version">v{{ $c->version }}</td><td data-l="Solde" class="num">{{ money($c->balance, $c->currency) }}</td><td data-l="Échéances">{{ $c->installments->count() }}</td>
                <td data-l="Respect">@if($cp['breached'])<span class="badge-st st-danger">Retard {{ money($cp['shortfall'], $c->currency) }}</span>@elseif(in_array($c->status, ['active', 'signed']))<span class="badge-st st-ok">Respecté</span>@else — @endif</td>
                <td data-l="Statut">{!! \App\Support\Labels::badge('contract', $c->status) !!}</td></tr>
        @empty<tr><td colspan="6">@include('partials.empty', ['text' => 'Aucun contrat.'])</td></tr>@endforelse</tbody>
    </table></div></div>

    <div class="tab-pane fade card-body" id="t-docs" role="tabpanel">
        <h2 class="h6 fw-bold text-navy">Documents financiers émis</h2>
        <div class="table-responsive mb-3"><table class="table table-sm table-stack"><thead><tr><th>N°</th><th>Type</th><th>Émis le</th><th>Statut</th><th></th></tr></thead><tbody>
        @forelse($issued as $d)<tr><td data-l="N°">{{ $d->number }}</td><td data-l="Type">{{ status_label('document_type', $d->type) }}</td><td data-l="Émis">{{ $d->issued_at->format('d/m/Y H:i') }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('document', $d->status) !!}</td><td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('documents.show', $d) }}" target="_blank" rel="noopener">Ouvrir</a></td></tr>
        @empty<tr><td colspan="5">@include('partials.empty', ['text' => 'Aucun document émis.'])</td></tr>@endforelse</tbody></table></div>
        <h2 class="h6 fw-bold text-navy">Pièces justificatives du dossier</h2>
        <ul class="list-group mb-3">@forelse($student->documents as $doc)<li class="list-group-item d-flex justify-content-between"><span>{{ $doc->label }} <small class="text-muted-2">({{ $doc->uploader->name }}, {{ $doc->created_at->format('d/m/Y') }})</small></span><a href="{{ route('students.documents.download', [$student, $doc]) }}">Télécharger</a></li>@empty<li class="list-group-item text-muted-2 small">Aucune pièce.</li>@endforelse</ul>
        @if(can_do('students.manage'))
            <form method="post" enctype="multipart/form-data" action="{{ route('students.documents.store', $student) }}" class="d-flex flex-wrap gap-2 align-items-end">@csrf
                <div><label class="form-label" for="dl">Libellé</label><input id="dl" name="label" class="form-control" required maxlength="150"></div>
                <div><label class="form-label" for="df">Fichier (PDF, JPG, PNG — 5 Mo)</label><input id="df" type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
                <button class="btn btn-light">Ajouter</button></form>
        @endif
    </div>

    <div class="tab-pane fade card-body" id="t-info" role="tabpanel">
        <dl class="dl-grid mb-3">
            <div><dt>Matricule</dt><dd>{{ $student->matricule }}</dd></div><div><dt>Sexe</dt><dd>{{ $student->gender ?? '—' }}</dd></div>
            <div><dt>Naissance</dt><dd>{{ $student->birth_date?->format('d/m/Y') ?? '—' }}</dd></div><div><dt>Téléphone</dt><dd>{{ $student->phone ?? '—' }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ $student->email ?? '—' }}</dd></div><div><dt>Adresse</dt><dd>{{ $student->address ?? '—' }}</dd></div>
        </dl>
        <h2 class="h6 fw-bold text-navy">Inscriptions</h2>
        <ul class="list-group mb-3">@foreach($student->enrollments->sortByDesc('academic_year_id') as $e)<li class="list-group-item">{{ $e->academicYear->code }} — {{ $e->promotion->code }} ({{ $e->promotion->name }}) — {{ status_label('enrollment_status', $e->status) }}{{ $e->state === 'cancelled' ? ' — annulée' : '' }}</li>@endforeach</ul>
        @if(can_do('students.manage') && $openYears->isNotEmpty())
            <form method="post" action="{{ route('students.enroll', $student) }}" class="d-flex flex-wrap gap-2 align-items-end">@csrf
                <div><label class="form-label" for="ey">Année</label><select id="ey" name="academic_year_id" class="form-select">@foreach($openYears as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
                <div><label class="form-label" for="ep">Promotion</label><select id="ep" name="promotion_id" class="form-select">@foreach($promotions as $p)<option value="{{ $p->id }}">{{ $p->code }}</option>@endforeach</select></div>
                <div><label class="form-label" for="es">Statut</label><select id="es" name="status" class="form-select">@foreach(\App\Support\Labels::STATUSES['enrollment_status'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <button class="btn btn-light">Inscrire</button></form>
        @endif
        @if(can_do('charges.manage') && $open)
            <hr><h2 class="h6 fw-bold text-navy">Créance manuelle (autres frais décidés par une personne habilitée)</h2>
            <form method="post" action="{{ route('charges.store', $student) }}" class="row g-2 align-items-end">@csrf
                <input type="hidden" name="academic_year_id" value="{{ $year->id }}"><input type="hidden" name="client_uuid" data-uuid>
                <div class="col-md-3"><label class="form-label" for="mc">Frais</label><select id="mc" name="fee_category_id" class="form-select" required>@foreach($allCategories as $c)<option value="{{ $c->id }}">{{ $c->label }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label" for="ma">Montant</label><input id="ma" name="amount" class="form-control text-end" inputmode="decimal" required></div>
                <div class="col-md-1"><label class="form-label" for="mcu">Devise</label><select id="mcu" name="currency" class="form-select">@foreach(\App\Models\Currency::pluck('code') as $cu)<option>{{ $cu }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label" for="md">Échéance</label><input id="md" type="date" name="due_date" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label" for="mr">Motif / décision</label><input id="mr" name="reason" class="form-control" required maxlength="255"></div>
                <div class="col-md-1"><button class="btn btn-light w-100">Créer</button></div></form>
        @endif
    </div>
</div>

@if(can_do('adjustments.request'))
<div class="modal fade" id="adjModal" tabindex="-1" aria-labelledby="adjTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" enctype="multipart/form-data" id="adjForm" data-action="{{ route('adjustments.store', ['charge' => '__ID__']) }}">@csrf
        <div class="modal-header"><h2 class="modal-title fs-5" id="adjTitle">Réduction, bourse ou exonération</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body">
            <p class="small text-muted-2" id="adjLabel"></p>
            <div class="mb-2"><label class="form-label" for="at">Type</label><select id="at" name="type" class="form-select">@foreach(['scholarship', 'reduction', 'exemption', 'sponsorship', 'decrease', 'increase'] as $t)<option value="{{ $t }}">{{ status_label('adjustment_type', $t) }}</option>@endforeach</select></div>
            <div class="mb-2"><label class="form-label" for="aa">Montant</label><input id="aa" name="amount" class="form-control text-end" inputmode="decimal" required></div>
            <div class="mb-2"><label class="form-label" for="as">Organisme (prise en charge, bourse)</label><input id="as" name="sponsor" class="form-control" maxlength="150"></div>
            <div class="mb-2"><label class="form-label" for="ar">Motif *</label><input id="ar" name="reason" class="form-control" required maxlength="255"></div>
            <div class="mb-2"><label class="form-label" for="aj">Justificatif (obligatoire pour bourse, exonération, prise en charge)</label><input id="aj" type="file" name="justification" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
            <p class="small text-muted-2 mb-0">Sans effet sur le solde tant qu’une personne habilitée, différente de vous, ne l’a pas approuvé.</p>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Soumettre</button></div>
    </form>
</div></div>
@endif
@endsection
@push('scripts')
<script>
document.getElementById('adjModal')?.addEventListener('show.bs.modal', (e) => {
    const b = e.relatedTarget, f = document.getElementById('adjForm');
    f.action = f.dataset.action.replace('__ID__', b.dataset.charge);
    document.getElementById('adjLabel').textContent = 'Créance : ' + b.dataset.label;
});
</script>
@endpush
