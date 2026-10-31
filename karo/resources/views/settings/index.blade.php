@extends('layouts.app')
@section('title', 'Paramètres')
@section('content')
@include('partials.head', ['title' => 'Paramètres', 'sub' => 'Aucun montant, aucune banque ni aucune promotion n’est codé dans l’application : tout se configure ici et dans « Frais ».'])
@php $tabs = array_filter(['inst' => can_do('settings.manage') ? 'Institution' : null, 'rules' => can_do('settings.manage') ? 'Règles et circuits' : null, 'struct' => can_do('structure.manage') ? 'Structure académique' : null,
    'fin' => can_do('finance_settings.manage') || can_do('rates.approve') ? 'Banques, devises, taux' : null, 'seq' => can_do('settings.manage') ? 'Numérotation' : null, 'bk' => can_do('backups.manage') ? 'Sauvegardes' : null]); @endphp
<ul class="nav nav-tabs" role="tablist">@foreach($tabs as $k => $l)<li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#t-{{ $k }}" type="button" role="tab">{{ $l }}</button></li>@endforeach</ul>
<div class="tab-content card border-top-0 rounded-top-0">
@if(isset($tabs['inst']))
<form class="tab-pane fade show active card-body" id="t-inst" method="post" action="{{ route('settings.institution') }}" enctype="multipart/form-data" role="tabpanel">@csrf @method('PUT')
    <div class="row g-3" style="max-width:960px">
        <div class="col-md-8"><label class="form-label" for="un">Nom de l’université *</label><input id="un" name="university_name" class="form-control" value="{{ $s['university_name'] }}" required></div>
        <div class="col-md-4"><label class="form-label" for="ua">Sigle</label><input id="ua" name="university_acronym" class="form-control" value="{{ $s['university_acronym'] }}"></div>
        <div class="col-md-6"><label class="form-label" for="uad">Adresse</label><input id="uad" name="university_address" class="form-control" value="{{ $s['university_address'] }}"></div>
        <div class="col-md-3"><label class="form-label" for="up">Téléphone</label><input id="up" name="university_phone" class="form-control" value="{{ $s['university_phone'] }}"></div>
        <div class="col-md-3"><label class="form-label" for="ue">E-mail</label><input id="ue" type="email" name="university_email" class="form-control" value="{{ $s['university_email'] }}"></div>
        <div class="col-md-6"><label class="form-label" for="ul">Logo (PNG/JPG, 1 Mo)</label><input id="ul" type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg"></div>
        <div class="col-md-6"><label class="form-label" for="tz">Fuseau horaire institutionnel</label><select id="tz" name="timezone" class="form-select">@foreach($timezones as $tz)<option @selected($s['timezone'] === $tz)>{{ $tz }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label" for="s1n">Signataire 1 — nom</label><input id="s1n" name="signatory_1_name" class="form-control" value="{{ $s['signatory_1_name'] }}"></div>
        <div class="col-md-3"><label class="form-label" for="s1t">Fonction</label><input id="s1t" name="signatory_1_title" class="form-control" value="{{ $s['signatory_1_title'] }}"></div>
        <div class="col-md-3"><label class="form-label" for="s2n">Signataire 2 — nom</label><input id="s2n" name="signatory_2_name" class="form-control" value="{{ $s['signatory_2_name'] }}"></div>
        <div class="col-md-3"><label class="form-label" for="s2t">Fonction</label><input id="s2t" name="signatory_2_title" class="form-control" value="{{ $s['signatory_2_title'] }}"></div>
    </div><button class="btn btn-primary mt-3">Enregistrer</button>
</form>
<form class="tab-pane fade card-body" id="t-rules" method="post" action="{{ route('settings.rules') }}" role="tabpanel">@csrf @method('PUT')
    <div class="row g-3" style="max-width:960px">
        @foreach(['segregation_of_duties' => 'Séparation des tâches : saisie, contrôle, validation et approbations par des personnes différentes', 'require_proof_for_submission' => 'Justificatif obligatoire pour soumettre un bordereau', 'require_check_before_validation' => 'Contrôle du justificatif obligatoire avant validation', 'adjustment_requires_approval' => 'Réductions et exonérations soumises à approbation', 'reconciliation_auto_exact' => 'Appliquer automatiquement les correspondances bancaires exactes et uniques', 'statement_reference_required' => 'Référence bancaire obligatoire (anomalie si absente)', 'document_verification_enabled' => 'QR code de vérification sur les documents', 'offline_enabled' => 'Autoriser la préparation de saisies hors connexion', 'student_portal_enabled' => 'Activer le portail étudiant'] as $k => $l)
            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="{{ $k }}" value="1" id="{{ $k }}" @checked($s[$k] === '1')><label class="form-check-label" for="{{ $k }}">{{ $l }}</label></div></div>
        @endforeach
        <div class="col-md-4"><label class="form-label" for="tol">Tolérance de date du rapprochement (jours)</label><input id="tol" type="number" name="reconciliation_date_tolerance_days" class="form-control" value="{{ $s['reconciliation_date_tolerance_days'] }}" min="0" max="30"></div>
        <div class="col-md-4"><label class="form-label" for="ttl">Durée de validité des données hors connexion (h)</label><input id="ttl" type="number" name="offline_ttl_hours" class="form-control" value="{{ $s['offline_ttl_hours'] }}"></div>
        <div class="col-md-4"><label class="form-label" for="mx">Taille max. d’un justificatif hors connexion (Ko)</label><input id="mx" type="number" name="offline_max_payload_kb" class="form-control" value="{{ $s['offline_max_payload_kb'] }}"></div>
        <div class="col-12"><label class="form-label" for="cdc">Conditions types des contrats</label><textarea id="cdc" name="contract_default_conditions" class="form-control" rows="3">{{ $s['contract_default_conditions'] }}</textarea></div>
    </div><button class="btn btn-primary mt-3" data-confirm="Modifier les règles financières ? La modification est journalisée.">Enregistrer</button>
</form>
@endif
@if(isset($tabs['struct']))
<div class="tab-pane fade card-body {{ array_key_first($tabs) === 'struct' ? 'show active' : '' }}" id="t-struct" role="tabpanel">
    @php
        $blocks = [
            'faculty' => ['Facultés', $faculties, fn ($x) => $x->code.' — '.$x->name, [['code', 'Code'], ['name', 'Nom']]],
            'department' => ['Départements', $departments, fn ($x) => $x->faculty->code.' › '.$x->code.' — '.$x->name, [['faculty_id', 'Faculté', $faculties], ['code', 'Code'], ['name', 'Nom']]],
            'cycle' => ['Cycles', $cycles, fn ($x) => $x->code.' — '.$x->name.' ('.$x->duration_years.' ans)', [['code', 'Code'], ['name', 'Nom'], ['duration_years', 'Durée (ans)']]],
            'program' => ['Filières', $programs, fn ($x) => $x->code.' — '.$x->name.' ('.$x->cycle->name.')', [['department_id', 'Département', $departments], ['cycle_id', 'Cycle', $cycles], ['code', 'Code'], ['name', 'Nom']]],
            'promotion' => ['Promotions', $promotions, fn ($x) => $x->code.' — '.$x->name.($x->is_final ? ' · finale' : ''), [['program_id', 'Filière', $programs], ['code', 'Code'], ['level', 'Niveau (Bac1, M2…)'], ['name', 'Nom']]],
        ];
    @endphp
    <div class="row g-3">@foreach($blocks as $type => [$title, $items, $label, $fields])
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">{{ $title }} ({{ $items->count() }})</div>
            <ul class="list-group list-group-flush" style="max-height:220px;overflow:auto">@forelse($items as $it)<li class="list-group-item d-flex justify-content-between small {{ ($it->is_active ?? true) ? '' : 'text-muted' }}"><span>{{ $label($it) }}</span>@if($type !== 'cycle')<form method="post" action="{{ route('settings.structure.toggle', [$type, $it->id]) }}">@csrf @method('PATCH')<button class="btn btn-link btn-sm p-0">{{ $it->is_active ? 'désactiver' : 'réactiver' }}</button></form>@endif</li>@empty<li class="list-group-item small text-muted-2">Aucun.</li>@endforelse</ul>
            <form method="post" action="{{ route('settings.structure.store', $type) }}" class="card-body row g-2 border-top">@csrf
                @foreach($fields as $f)<div class="col-md-{{ 12 / min(4, count($fields)) }}"><label class="form-label" for="{{ $type }}-{{ $f[0] }}">{{ $f[1] }}</label>
                    @if(isset($f[2]))<select id="{{ $type }}-{{ $f[0] }}" name="{{ $f[0] }}" class="form-select form-select-sm" required>@foreach($f[2] as $o)<option value="{{ $o->id }}">{{ $o->code }}</option>@endforeach</select>
                    @else<input id="{{ $type }}-{{ $f[0] }}" name="{{ $f[0] }}" class="form-control form-control-sm" required>@endif</div>@endforeach
                @if($type === 'promotion')<div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_final" value="1" id="isf"><label class="form-check-label small" for="isf">Promotion finale (entérinement du diplôme)</label></div>@endif
                <div class="col-12"><button class="btn btn-sm btn-light">Ajouter</button></div></form>
        </div></div>
    @endforeach</div>
</div>
@endif
@if(isset($tabs['fin']))
<div class="tab-pane fade card-body {{ array_key_first($tabs) === 'fin' ? 'show active' : '' }}" id="t-fin" role="tabpanel"><div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-header">Banques et comptes bénéficiaires</div>
        <ul class="list-group list-group-flush">@foreach($banks as $b)<li class="list-group-item"><strong>{{ $b->code }} — {{ $b->name }}</strong>@foreach($b->accounts as $a)<div class="d-flex justify-content-between small ms-3 {{ $a->is_active ? '' : 'text-muted' }}"><span>{{ $a->account_number }} — {{ $a->label }} ({{ $a->currency }})</span>@if(can_do('finance_settings.manage'))<form method="post" action="{{ route('settings.accounts.toggle', $a) }}">@csrf @method('PATCH')<button class="btn btn-link btn-sm p-0">{{ $a->is_active ? 'désactiver' : 'activer' }}</button></form>@endif</div>@endforeach</li>@endforeach</ul>
        @if(can_do('finance_settings.manage'))
        <div class="card-body border-top row g-2">
            <form method="post" action="{{ route('settings.banks.store') }}" class="col-12 d-flex flex-wrap gap-2 align-items-end">@csrf<div><label class="form-label" for="bc">Code banque</label><input id="bc" name="code" class="form-control form-control-sm" required></div><div class="flex-grow-1"><label class="form-label" for="bn">Nom</label><input id="bn" name="name" class="form-control form-control-sm" required></div><button class="btn btn-sm btn-light">Ajouter la banque</button></form>
            <form method="post" action="{{ route('settings.accounts.store') }}" class="col-12 d-flex flex-wrap gap-2 align-items-end">@csrf<div><label class="form-label" for="ab">Banque</label><select id="ab" name="bank_id" class="form-select form-select-sm">@foreach($banks as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach</select></div><div><label class="form-label" for="an">N° de compte</label><input id="an" name="account_number" class="form-control form-control-sm" required></div><div><label class="form-label" for="al">Libellé</label><input id="al" name="label" class="form-control form-control-sm" required></div><div><label class="form-label" for="ac">Devise</label><select id="ac" name="currency" class="form-select form-select-sm">@foreach($currencies as $c)<option>{{ $c->code }}</option>@endforeach</select></div><button class="btn btn-sm btn-light">Ajouter le compte</button></form>
        </div>@endif
    </div></div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">Devises</div><div class="card-body small">@foreach($currencies as $c)<span class="badge text-bg-light border me-1">{{ $c->code }} {{ $c->symbol }}</span>@endforeach
            @if(can_do('finance_settings.manage'))<form method="post" action="{{ route('settings.currencies.store') }}" class="d-flex gap-1 mt-2">@csrf<input name="code" class="form-control form-control-sm" placeholder="EUR" maxlength="3" required aria-label="Code"><input name="name" class="form-control form-control-sm" placeholder="Nom" required aria-label="Nom"><input name="symbol" class="form-control form-control-sm" placeholder="€" required aria-label="Symbole"><button class="btn btn-sm btn-light">+</button></form>@endif</div></div>
        <div class="card"><div class="card-header">Taux de conversion approuvés</div>
            <ul class="list-group list-group-flush small">@forelse($rates as $r)<li class="list-group-item">1 {{ $r->from_currency }} = {{ rtrim(rtrim($r->rate, '0'), '.') }} {{ $r->to_currency }} au {{ $r->effective_on->format('d/m/Y') }} — {{ $r->source }} — approuvé par {{ $r->approver->name }}</li>@empty<li class="list-group-item text-muted-2">Aucun : aucune opération entre devises n’est possible.</li>@endforelse</ul>
            @if(can_do('rates.approve'))<form method="post" action="{{ route('settings.rates.store') }}" class="card-body row g-2 border-top" data-confirm="Approuver ce taux ? Il servira aux opérations entre devises à partir de sa date.">@csrf
                <div class="col-4"><label class="form-label" for="rf">De</label><select id="rf" name="from_currency" class="form-select form-select-sm">@foreach($currencies as $c)<option>{{ $c->code }}</option>@endforeach</select></div>
                <div class="col-4"><label class="form-label" for="rt">Vers</label><select id="rt" name="to_currency" class="form-select form-select-sm">@foreach($currencies->reverse() as $c)<option>{{ $c->code }}</option>@endforeach</select></div>
                <div class="col-4"><label class="form-label" for="rr">Taux</label><input id="rr" name="rate" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label" for="re">Date d’effet</label><input id="re" type="date" name="effective_on" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label" for="rs">Source / décision</label><input id="rs" name="source" class="form-control form-control-sm" required></div>
                <div class="col-12"><button class="btn btn-sm btn-primary">Approuver le taux</button></div></form>@endif
        </div>
    </div>
</div></div>
@endif
@if(isset($tabs['seq']))
<form class="tab-pane fade card-body" id="t-seq" method="post" action="{{ route('settings.sequences') }}" role="tabpanel">@csrf @method('PUT')
    <table class="table table-sm" style="max-width:760px"><thead><tr><th>Document</th><th>Préfixe</th><th>Chiffres</th><th>Prochain numéro</th></tr></thead><tbody>
    @foreach($sequences as $q)<tr><td>{{ $q->label }}</td><td><input name="seq[{{ $q->id }}][prefix]" class="form-control form-control-sm" value="{{ $q->prefix }}" aria-label="Préfixe"></td><td><input type="number" name="seq[{{ $q->id }}][padding]" class="form-control form-control-sm" value="{{ $q->padding }}" min="3" max="10" aria-label="Chiffres"></td><td class="small">{{ $q->prefix }}-{{ now()->year }}-{{ str_pad($q->next_number, $q->padding, '0', STR_PAD_LEFT) }}</td></tr>@endforeach
    </tbody></table><button class="btn btn-primary">Enregistrer</button>
</form>
@endif
@if(isset($tabs['bk']))
<div class="tab-pane fade card-body {{ array_key_first($tabs) === 'bk' ? 'show active' : '' }}" id="t-bk" role="tabpanel">
    @unless($backupKeyOk)<div class="alert alert-danger">Clé de chiffrement des sauvegardes absente (BACKUP_ENCRYPTION_KEY). Générez-la : <code>php artisan karo:backup-key</code>, et conservez-en une copie hors du serveur.</div>@endunless
    <p class="small">Sauvegardes chiffrées (AES-256-GCM) de la base et des justificatifs. Copie séparée : <strong>{{ $secondary ?: 'non configurée (BACKUP_SECONDARY_PATH)' }}</strong>. Automatisation : <code>php artisan karo:backup</code> planifié chaque nuit. Restauration : <code>php artisan karo:restore fichier.kbk</code> (voir la documentation).</p>
    <form method="post" action="{{ route('settings.backup') }}" data-confirm="Créer une sauvegarde chiffrée maintenant ?">@csrf<button class="btn btn-primary" @disabled(!$backupKeyOk)><i class="bi bi-database-lock"></i> Créer une sauvegarde</button></form>
    <table class="table table-sm mt-3" style="max-width:760px"><thead><tr><th>Fichier</th><th>Date</th><th class="num">Taille</th><th></th></tr></thead><tbody>
    @forelse($backups as $b)<tr><td>{{ $b['name'] }}</td><td>{{ $b['date'] }}</td><td class="num">{{ number_format($b['size'] / 1048576, 2, ',', ' ') }} Mo</td><td><a href="{{ route('settings.backup.download', $b['name']) }}">Télécharger</a></td></tr>@empty<tr><td colspan="4" class="text-muted-2 small">Aucune sauvegarde.</td></tr>@endforelse
    </tbody></table>
</div>
@endif
</div>
@endsection
