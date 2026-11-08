@extends('layouts.app')
@section('title', $employee->fullName())
@php
    $u = auth()->user();
    $can = fn ($p) => $u->canOnEmployee($p, $employee);
    $ended = $employee->isEnded();
    $opt = fn ($c, $label = 'name') => $c->mapWithKeys(fn ($x) => [$x->id => $x->$label])->all();
    $d = fn ($v) => $v ? $v->format('d/m/Y') : '—';
    $L = fn ($g, $v) => \App\Support\Labels::status($g, $v);
    $B = fn ($g, $v) => \App\Support\Labels::badge($g, $v);
@endphp
@section('content')
<div class="crumb"><a href="{{ route('employees.index') }}">Agents</a> ›</div>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-start">
    @if($employee->photo_path)<img src="{{ route('employees.photo.show', $employee) }}" class="photo-id" alt="Photo d’identité">@else<div class="photo-placeholder" aria-hidden="true"><i class="bi bi-person"></i></div>@endif
    <div class="flex-grow-1" style="min-width:240px">
        <h1 class="h4 fw-bold text-navy mb-1">{{ $employee->fullName() }}</h1>
        <div class="mb-2"><span class="badge text-bg-light border">{{ $employee->matricule }}</span> {!! $B('employee', $employee->status) !!} <span class="small text-muted-2">depuis le {{ $d($employee->status_since) }}</span></div>
        <div class="row g-2 small">
            <div class="col-sm-6 col-xl-3"><span class="text-muted-2">Famille</span><br><b>{{ $L('family', $employee->staff_family) }}</b></div>
            <div class="col-sm-6 col-xl-3"><span class="text-muted-2">Grade</span><br><b>{{ $employee->grade?->name ?? '—' }}</b>@if($employee->mandate) · {{ $employee->mandate->name }}@endif</div>
            <div class="col-sm-6 col-xl-3"><span class="text-muted-2">Affectation principale</span><br><b>{{ $employee->activeAssignment?->orgUnit?->name ?? '—' }}</b></div>
            <div class="col-sm-6 col-xl-3"><span class="text-muted-2">Fonction</span><br><b>{{ $employee->jobFunction?->name ?? '—' }}</b></div>
        </div>
        @if($employee->mandate_end && ! $ended)<div class="small mt-2 {{ $employee->mandate_end->lte(now()->addDays((int) config('grh.alert_days_mandate'))) ? 'text-danger fw-600' : 'text-muted-2' }}"><i class="bi bi-alarm"></i> Fin du mandat : {{ $employee->mandate_end->format('d/m/Y') }}</div>@endif
        @if($ended)<div class="alert alert-secondary small mt-2 mb-0 py-2"><i class="bi bi-archive"></i> Dossier conservé intégralement. Fin des droits ordinaires le {{ $d($employee->rights_end_date) }}.</div>@endif
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">
        @if($can('employees.manage'))<a href="{{ route('employees.edit', $employee) }}" class="btn btn-light"><i class="bi bi-pencil"></i> Modifier</a>@endif
        <a href="{{ route('employees.sheet', $employee) }}" class="btn btn-light" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Fiche individuelle</a>
        @php $acts = array_filter([
            ['career', 'career.manage', 'bi-graph-up-arrow', 'Nomination / promotion'], ['assign', 'assignments.manage', 'bi-diagram-3', 'Nouvelle affectation'],
            ['situation', 'situations.manage', 'bi-calendar2-week', 'Congé / situation'], ['mutation', 'mutations.manage', 'bi-arrow-left-right', 'Mutation'],
            ['qualif', 'qualifications.manage', 'bi-mortarboard', 'Diplôme / thèse'], ['doc', 'employees.documents', 'bi-paperclip', 'Pièce numérisée'],
            ['death', 'deaths.manage', 'bi-flower1', 'Enregistrer un décès'],
        ], fn ($a) => $can($a[1]) && (! $ended || in_array($a[0], ['qualif', 'doc'], true))); @endphp
        @if($acts)
        <div class="dropdown"><button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-lightning"></i> Actions</button>
            <ul class="dropdown-menu dropdown-menu-end">@foreach($acts as [$m, $p, $i, $l])<li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m-{{ $m }}"><i class="bi {{ $i }} me-2"></i>{{ $l }}</button></li>@endforeach</ul></div>
        @endif
        @if($can('employees.manage'))
        <form method="post" action="{{ route('employees.photo', $employee) }}" enctype="multipart/form-data" class="d-flex gap-1">@csrf<label class="btn btn-light mb-0" title="Photo d’identité (JPG/PNG)"><i class="bi bi-camera"></i><input type="file" name="photo" accept=".jpg,.jpeg,.png" class="d-none" data-autosubmit></label></form>
        @endif
    </div>
</div></div>

<ul class="nav nav-tabs mb-3" role="tablist">
    @foreach(array_filter(['dossier' => 'Dossier', 'carriere' => 'Carrière ('.$career->count().')', 'diplomes' => 'Diplômes ('.$qualifications->count().')', 'affectations' => 'Affectations ('.$assignments->count().')', 'situations' => 'Congés et situations ('.$situations->count().')', 'mouvements' => 'Mutations et décès', 'finances' => $canFinance ? 'Primes et soutiens' : null, 'documents' => 'Pièces ('.$documents->count().')', 'historique' => $canHistory ? 'Historique' : null]) as $k => $l)
        <li class="nav-item" role="presentation"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $k }}" type="button" role="tab">{{ $l }}</button></li>
    @endforeach
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="dossier" role="tabpanel">
    <div class="row g-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Identité et état civil</div><div class="card-body"><dl class="kv">
            <dt>Nom, postnom, prénom</dt><dd>{{ $employee->fullName() }}</dd><dt>Sexe</dt><dd>{{ $L('sex', $employee->sex) }}</dd>
            <dt>Naissance</dt><dd>{{ $d($employee->birth_date) }} @if($employee->birth_place) à {{ $employee->birth_place }}@endif</dd><dt>Nationalité</dt><dd>{{ $employee->nationality }}</dd>
            <dt>État civil</dt><dd>{{ $L('marital', $employee->marital_status) ?: '—' }} · {{ $employee->children_count }} enfant(s)</dd><dt>Situation familiale</dt><dd>{{ $employee->family_notes ?: '—' }}</dd>
            <dt>Adresse</dt><dd>{{ $employee->address ?: '—' }}</dd><dt>Téléphone</dt><dd>{{ $employee->phone ?: '—' }}</dd><dt>Courriel</dt><dd>{{ $employee->email ?: '—' }}</dd>
            <dt>Personne à contacter</dt><dd>{{ $employee->emergency_contact_name ?: '—' }} {{ $employee->emergency_contact_phone }} @if($employee->emergency_contact_relation)({{ $employee->emergency_contact_relation }})@endif</dd>
            <dt>Compte utilisateur</dt><dd>{{ $employee->user ? $employee->user->username : 'Aucun (pas d’accès à l’espace personnel)' }}</dd>
        </dl></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Situation administrative</div><div class="card-body"><dl class="kv">
            <dt>Famille</dt><dd>{{ $L('family', $employee->staff_family) }}</dd><dt>Catégorie</dt><dd>{{ $employee->category?->name ?? '—' }}</dd>
            <dt>Grade</dt><dd>{{ $employee->grade?->name ?? '—' }}</dd><dt>Mandat</dt><dd>{{ $employee->mandate?->name ?? '—' }} @if($employee->mandate_start)({{ $d($employee->mandate_start) }} → {{ $d($employee->mandate_end) }})@endif</dd>
            @if($employee->assistant_track)<dt>Fonction d’assistant</dt><dd>{{ $L('track', $employee->assistant_track) }}</dd>@endif
            @if($employee->adminGrade)<dt>Catégorie administrative</dt><dd>{{ $employee->adminGrade->name }}</dd>@endif
            <dt>Fonction / responsabilité</dt><dd>{{ $employee->jobFunction?->name ?? '—' }}</dd>
            <dt>Faculté ou service</dt><dd>{{ $employee->activeAssignment?->orgUnit?->pathName() ?? '—' }}</dd><dt>Poste</dt><dd>{{ $employee->activeAssignment?->position?->title ?? '—' }}</dd>
            <dt>Première prise de service</dt><dd>{{ $d($employee->first_service_date) }}</dd><dt>Engagement à l’université</dt><dd>{{ $d($employee->engagement_date) }}</dd>
            <dt>Situation</dt><dd>{!! $B('employee', $employee->status) !!}</dd>
        </dl></div></div></div>
    </div>
</div>

<div class="tab-pane fade" id="carriere" role="tabpanel"><div class="card"><div class="card-header"><span>Chronologie de carrière</span>@if($can('career.manage') && ! $ended)<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#m-career"><i class="bi bi-plus"></i> Décision</button>@endif</div><div class="card-body">
    @if($career->count())
    <ul class="timeline">@foreach($career as $ev)
        <li class="{{ $ev->status === 'draft' ? 'warn' : ($ev->status === 'validated' ? '' : 'muted') }}"><span class="dot"></span>
            <div class="when">{{ $d($ev->effective_date) }} · {!! $B('decision', $ev->status) !!}</div>
            <div class="what">{{ $L('career_type', $ev->type) }} @if($ev->newGrade)— {{ $ev->oldGrade?->name ?? '…' }} → {{ $ev->newGrade->name }}@endif @if($ev->newMandate)— {{ $ev->oldMandate?->name ?? '…' }} → {{ $ev->newMandate->name }}@endif @if($ev->newCategory) — catégorie {{ $ev->newCategory->name }}@endif</div>
            <div class="small text-muted-2">Décision {{ $ev->decision_number }} du {{ $d($ev->decision_date) }} · {{ $ev->signing_authority }} @if($ev->document)· <a href="{{ route('documents.download', $ev->document) }}" target="_blank">pièce</a>@endif</div>
            @if($ev->observations)<div class="small">{{ $ev->observations }}</div>@endif
            <div class="mini text-muted-2">Saisie : {{ $ev->author?->name ?? '—' }} @if($ev->validator)· {{ $ev->status === 'rejected' ? 'rejetée' : 'validée' }} par {{ $ev->validator->name }}@endif @if($ev->rejection_reason)— {{ $ev->rejection_reason }}@endif</div>
            @if($ev->status === 'draft' && $can('career.validate'))
                <div class="d-flex gap-2 mt-1 flex-wrap">
                    <form method="post" action="{{ route('career.validate', $ev) }}" data-confirm="Valider cette décision ? Le dossier sera mis à jour.">@csrf<button class="btn btn-sm btn-primary">Valider</button></form>
                    <form method="post" action="{{ route('career.reject', $ev) }}" class="d-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif du rejet" required><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>
                </div>
            @endif
        </li>
    @endforeach</ul>
    @else @include('partials.empty', ['text' => 'Aucune décision de carrière enregistrée.']) @endif
</div></div></div>

<div class="tab-pane fade" id="diplomes" role="tabpanel"><div class="card"><div class="card-header"><span>Diplômes et production scientifique</span>@if($can('qualifications.manage'))<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#m-qualif"><i class="bi bi-plus"></i> Ajouter</button>@endif</div>
    @if($qualifications->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Nature</th><th>Intitulé</th><th>Domaine</th><th>Institution / université</th><th>Soutenance</th><th>Année</th><th>Mention</th><th>Directeur</th><th></th></tr></thead><tbody>
    @foreach($qualifications as $q)<tr><td data-l="Nature" class="fw-600">{{ $L('qualification', $q->nature) }}</td><td data-l="Intitulé">{{ $q->title }}</td><td data-l="Domaine">{{ $q->field }}</td><td data-l="Institution">{{ $q->institution }} @if($q->defense_university && $q->defense_university !== $q->institution)<br><span class="small text-muted-2">Soutenance : {{ $q->defense_university }}</span>@endif</td><td data-l="Soutenance">{{ $d($q->defense_date) }}</td><td data-l="Année">{{ $q->academicYear?->code }}</td><td data-l="Mention">{{ $q->mention }}</td><td data-l="Directeur">{{ $q->supervisor }}</td><td>@if($q->document)<a href="{{ route('documents.download', $q->document) }}" target="_blank" title="Justificatif"><i class="bi bi-paperclip"></i></a>@endif</td></tr>@endforeach
    </tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucun diplôme enregistré.'])</div> @endif
</div></div>

<div class="tab-pane fade" id="affectations" role="tabpanel"><div class="card"><div class="card-header"><span>Historique des affectations</span>@if($can('assignments.manage') && ! $ended)<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#m-assign"><i class="bi bi-plus"></i> Affectation</button>@endif</div>
    @if($assignments->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Période</th><th>Structure d’origine</th><th>Structure d’accueil</th><th>Poste / fonction</th><th>Motif</th><th>Décision</th><th>Statut</th><th></th></tr></thead><tbody>
    @foreach($assignments as $a)<tr><td data-l="Période" class="nw">{{ $d($a->start_date) }} → {{ $a->end_date ? $d($a->end_date) : '…' }}</td><td data-l="Origine">{{ $a->fromUnit?->name ?? '—' }}</td><td data-l="Accueil" class="fw-600">{{ $a->orgUnit?->name }} @unless($a->is_primary)<span class="badge text-bg-light border">secondaire</span>@endunless</td><td data-l="Poste">{{ $a->position?->title ?? '' }} {{ $a->jobFunction ? '· '.$a->jobFunction->name : '' }}</td><td data-l="Motif">{{ $a->reason }} @if($a->closure_reason)<br><span class="small text-muted-2">Clôture : {{ $a->closure_reason }}</span>@endif</td><td data-l="Décision">{{ $a->decision_number }} {{ $a->authority ? '· '.$a->authority : '' }} @if($a->document)<a href="{{ route('documents.download', $a->document) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endif</td><td data-l="Statut">{!! $B('assignment', $a->status) !!}</td>
        <td>@if($a->status === 'active' && $can('assignments.manage'))<form method="post" action="{{ route('assignments.close', $a) }}" class="d-flex gap-1" data-confirm="Clôturer cette affectation ?">@csrf<input type="date" name="end_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required aria-label="Date de fin"><input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif"><button class="btn btn-sm btn-outline-secondary">Clôturer</button></form>@endif</td></tr>@endforeach
    </tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucune affectation.'])</div> @endif
</div></div>

<div class="tab-pane fade" id="situations" role="tabpanel"><div class="card"><div class="card-header"><span>Congés et situations administratives</span>@if($can('situations.manage') && ! $ended)<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#m-situation"><i class="bi bi-plus"></i> Situation</button>@endif</div>
    @if($situations->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Type</th><th>Période</th><th>Durée</th><th>Motif</th><th>Décision</th><th>Effet sur les primes</th><th>Statut</th><th></th></tr></thead><tbody>
    @foreach($situations as $s)<tr><td data-l="Type" class="fw-600">{{ $s->type->name }} @if($s->extends_id)<span class="badge text-bg-light border">prolongation</span>@endif</td><td data-l="Période" class="nw">{{ $d($s->start_date) }} → {{ $s->actual_end_date ? $d($s->actual_end_date) : ($s->planned_end_date ? $d($s->planned_end_date).' (prévue)' : 'ouverte') }}</td><td data-l="Durée">{{ $s->durationDays() ? $s->durationDays().' j' : '—' }}</td><td data-l="Motif">{{ $s->reason }}</td><td data-l="Décision">{{ $s->decision_reference }} {{ $s->authority ? '· '.$s->authority : '' }} @if($s->document)<a href="{{ route('documents.download', $s->document) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endif</td><td data-l="Primes">{{ $s->type->prime_percent }} %</td><td data-l="Statut">{!! $B('situation', $s->status) !!}</td>
        <td class="nw">@include('situations._actions', ['s' => $s])</td></tr>@endforeach
    </tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucune situation enregistrée.'])</div> @endif
</div></div>

<div class="tab-pane fade" id="mouvements" role="tabpanel"><div class="row g-3">
    <div class="col-lg-7"><div class="card h-100"><div class="card-header"><span>Mutations</span>@if($can('mutations.manage') && ! $ended)<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#m-mutation"><i class="bi bi-plus"></i> Mutation</button>@endif</div>
        @if($mutations->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Effet</th><th>Nature</th><th>Destination</th><th>Décision</th><th>Statut</th></tr></thead><tbody>
        @foreach($mutations as $m)<tr><td data-l="Effet">{{ $d($m->effective_date) }}</td><td data-l="Nature">{{ $L('mutation_kind', $m->kind) }}</td><td data-l="Destination">{{ $m->kind === 'internal' ? $m->toUnit?->name : $m->destination_institution }}<br><span class="small text-muted-2">{{ $m->reason }}</span></td><td data-l="Décision">{{ $m->decision_reference }}</td><td data-l="Statut">{!! $B('decision', $m->status) !!}</td></tr>@endforeach
        </tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucune mutation.'])</div> @endif
    </div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-header">Décès</div><div class="card-body">
        @if($employee->death)
            <dl class="kv"><dt>Date</dt><dd>{{ $d($employee->death->death_date) }}</dd><dt>Lieu</dt><dd>{{ $employee->death->death_place ?: '—' }}</dd><dt>Statut</dt><dd>{!! $B('decision', $employee->death->status) !!}</dd>
            @if($u->hasPermission('deaths.cause'))<dt>Cause (confidentiel)</dt><dd>{{ $employee->death->cause ?: 'Non renseignée' }}</dd>@endif
            <dt>Contact</dt><dd>{{ $employee->death->contact_name }} {{ $employee->death->contact_phone }}</dd></dl>
            <a href="{{ route('deaths.index') }}" class="small">Voir le registre des décès</a>
        @else <p class="text-muted-2 small mb-0">Aucun décès enregistré.</p> @endif
    </div></div></div>
</div></div>

@if($canFinance)
<div class="tab-pane fade" id="finances" role="tabpanel"><div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-header">Situation des primes (par devise)</div><div class="card-body">
        @forelse($summary as $cur => $t)
            <div class="row g-2 mb-2">@foreach([['Prévu', 'net'], ['Payé', 'paid'], ['Acomptes', 'advances'], ['Solde', 'balance'], ['Arriérés', 'arrears'], ['Trop-perçu', 'overpaid']] as [$l, $k])<div class="col-6 col-md-4 col-xl-2"><div class="small text-muted-2">{{ $l }}</div><div class="fw-600 money">{{ money($t[$k], $cur) }}</div></div>@endforeach</div>
        @empty <p class="text-muted-2 small mb-0">Aucun droit à prime généré.</p> @endforelse
    </div>
    @if($entitlements->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Période</th><th>Prime</th><th class="num">Prévu</th><th class="num">Payé</th><th class="num">Solde</th><th>Statut</th></tr></thead><tbody>
    @foreach($entitlements as $en)<tr><td data-l="Période"><a href="{{ route('primes.show', $en) }}">{{ $en->period_label }}</a></td><td data-l="Prime">{{ $en->primeType?->name }}</td><td data-l="Prévu" class="num">{{ money($en->netAmount(), $en->currency) }}</td><td data-l="Payé" class="num">{{ money($en->paidAmount(), $en->currency) }}</td><td data-l="Solde" class="num fw-600">{{ money($en->balance(), $en->currency) }}</td><td data-l="Statut">{!! $B('entitlement', $en->status) !!}</td></tr>@endforeach
    </tbody></table></div>@endif
    </div>
    <div class="card mt-3"><div class="card-header">Soutiens sociaux</div>
        @if($supports->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>N°</th><th>Type</th><th>Objet</th><th class="num">Montant</th><th>Statut</th></tr></thead><tbody>@foreach($supports as $sp)<tr><td data-l="N°">{{ $sp->number }}</td><td data-l="Type">{{ $sp->type->name }}</td><td data-l="Objet">{{ $sp->deceased_name ? $sp->deceased_name.' ('.$sp->kinship?->name.')' : $sp->description }}</td><td data-l="Montant" class="num">{{ $sp->amount_granted !== null ? money($sp->amount_granted, $sp->currency) : ($sp->amount_requested !== null ? money($sp->amount_requested, $sp->currency).' demandé' : '—') }}</td><td data-l="Statut">{!! $B('support', $sp->status) !!}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucun soutien.</div> @endif
    </div></div>
    <div class="col-lg-4"><div class="card sensitive"><div class="card-header"><i class="bi bi-shield-lock"></i> Coordonnées bancaires</div><div class="card-body">
        @if($canBank)
            <form method="post" action="{{ route('employees.bank', $employee) }}">@csrf @method('PUT')<div class="row g-2">
                @include('partials.field', ['name' => 'bank_name', 'label' => 'Banque', 'value' => $employee->bank_name, 'col' => 'col-12'])
                @include('partials.field', ['name' => 'bank_account', 'label' => 'Numéro de compte', 'value' => $employee->bank_account, 'col' => 'col-12'])
                @include('partials.field', ['name' => 'bank_holder', 'label' => 'Titulaire', 'value' => $employee->bank_holder, 'col' => 'col-12'])
                <div class="col-12"><button class="btn btn-sm btn-primary">Enregistrer</button> <span class="mini text-muted-2">Chiffrées en base ; consultations journalisées.</span></div>
            </div></form>
        @else <p class="small text-muted-2 mb-0">Accès réservé aux personnes habilitées (permission « coordonnées bancaires »).</p> @endif
    </div></div></div>
</div></div>
@endif

<div class="tab-pane fade" id="documents" role="tabpanel"><div class="card"><div class="card-header"><span>Pièces numérisées</span>@if($can('employees.documents'))<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#m-doc"><i class="bi bi-plus"></i> Ajouter</button>@endif</div>
    @if($documents->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Date</th><th>Type</th><th>Intitulé</th><th>Référence</th><th>Autorité signataire</th><th>Taille</th></tr></thead><tbody>
    @foreach($documents as $doc)<tr><td data-l="Date">{{ $d($doc->document_date) }}</td><td data-l="Type">{{ $doc->type?->name ?? '—' }}</td><td data-l="Intitulé"><a href="{{ route('documents.download', $doc) }}" target="_blank"><i class="bi bi-file-earmark"></i> {{ $doc->title }}</a></td><td data-l="Référence">{{ $doc->reference }}</td><td data-l="Autorité">{{ $doc->signing_authority }}</td><td data-l="Taille">{{ number_format($doc->size / 1024, 0, ',', ' ') }} Ko</td></tr>@endforeach
    </tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucune pièce numérisée.'])</div> @endif
</div></div>

@if($canHistory)
<div class="tab-pane fade" id="historique" role="tabpanel"><div class="card"><div class="card-header">Historique des modifications (200 dernières)</div>
    <div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Date</th><th>Auteur</th><th>Objet</th><th>Champ</th><th>Ancienne valeur</th><th>Nouvelle valeur</th><th>Contexte</th></tr></thead><tbody>
    @forelse($history as $h)<tr><td data-l="Date" class="nw">{{ $h->created_at?->format('d/m/Y H:i') }}</td><td data-l="Auteur">{{ $h->user?->name ?? 'Système' }}</td><td data-l="Objet">{{ class_basename($h->subject_type) }}</td><td data-l="Champ"><code>{{ $h->field }}</code></td><td data-l="Ancienne">{{ \Illuminate\Support\Str::limit((string) $h->old_value, 60) }}</td><td data-l="Nouvelle">{{ \Illuminate\Support\Str::limit((string) $h->new_value, 60) }}</td><td data-l="Contexte">{{ $h->context }}</td></tr>@empty<tr><td colspan="7" class="text-muted-2">Aucune modification.</td></tr>@endforelse
    </tbody></table></div>
</div></div>
@endif
</div>

@include('employees._modals')
@endsection
