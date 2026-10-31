@extends('layouts.app')
@section('title', 'Import '.$batch->number)
@section('content')
@include('partials.head', ['title' => 'Import '.$batch->number, 'crumbs' => ['Import Excel' => route('imports.students.create')], 'sub' => e($batch->original_name).' · '.$batch->rows_total.' ligne(s) · inscriptions '.e($year?->code)])
@if($batch->options['already_imported_as'] ?? null)<div class="alert alert-info">Ce fichier identique a déjà été importé (lot {{ $batch->options['already_imported_as'] }}). Le réimporter ne crée aucun doublon : seuls les éléments manquants seraient ajoutés.</div>@endif
@if($batch->status === 'completed')
    <div class="alert alert-success d-flex justify-content-between flex-wrap gap-2"><span>Import terminé : {{ $batch->rows_created }} dossier(s) créé(s), {{ $batch->rows_updated }} mis à jour, {{ $batch->rows_skipped }} ignoré(s).</span><a href="{{ route('imports.students.report', $batch) }}" class="btn btn-sm btn-light"><i class="bi bi-download"></i> Rapport d’import</a></div>
@elseif(in_array($batch->status, ['processing', 'failed']))
    <div class="alert alert-{{ $batch->status === 'failed' ? 'danger' : 'info' }}">{{ $batch->status === 'failed' ? 'Échec du traitement : '.($batch->report['__erreur'] ?? '') : 'Traitement en cours en arrière-plan…' }}</div>
@endif

@if(in_array($batch->status, ['uploaded', 'previewed']))
<form method="post" action="{{ route('imports.students.preview', $batch) }}" class="card mb-3">@csrf
    <div class="card-header">2. Associer les colonnes du fichier aux champs attendus</div>
    <div class="card-body row g-2">
        @foreach($fields as $f => [$label, $req])
            <div class="col-md-4 col-xl-3"><label class="form-label" for="map-{{ $f }}">{{ $label }} {{ $req ? '*' : '' }}</label>
                <select id="map-{{ $f }}" name="map[{{ $f }}]" class="form-select form-select-sm" @required($req)><option value="">— Non importé —</option>@foreach($headers as $i => $h)<option value="{{ $i }}" @selected(isset($batch->column_map[$f]) && (int) $batch->column_map[$f] === $i)>{{ $h !== '' ? $h : 'Colonne '.($i + 1) }}</option>@endforeach</select></div>
        @endforeach
        <div class="col-12 form-check ms-2 mt-2"><input class="form-check-input" type="checkbox" name="update_existing" value="1" id="ue" @checked($batch->options['update_existing'] ?? false)><label class="form-check-label" for="ue">Mettre à jour les dossiers existants dont les données diffèrent (action explicite, tracée)</label></div>
    </div>
    <div class="card-footer bg-white"><button class="btn btn-primary"><i class="bi bi-eye"></i> Prévisualiser et contrôler</button></div>
</form>
@endif

@if($analysis)
    @php $s = $analysis['summary']; @endphp
    <div class="row g-2 mb-3">
        @foreach([['valid', 'Lignes valides', 'accent'], ['error', 'Lignes en erreur', 'alert-k'], ['new', 'Nouveaux dossiers', ''], ['update', 'Mises à jour', 'warn-k'], ['unchanged', 'Inchangés / ignorés', '']] as [$k, $l, $c])
            <div class="col-6 col-md"><div class="card kpi {{ $c }}"><div class="l">{{ $l }}</div><div class="v">{{ $s[$k] }}</div></div></div>
        @endforeach
    </div>
    <div class="card mb-3"><div class="card-header">3. Prévisualisation ligne par ligne <span class="small fw-normal text-muted-2">aucune donnée n’est encore enregistrée</span></div>
        <div class="table-responsive" style="max-height:480px"><table class="table table-sm table-stack mb-0">
            <thead><tr><th>Ligne</th><th>Matricule</th><th>Nom</th><th>Promotion</th><th>Statut</th><th>Action</th><th>Erreurs / remarques</th></tr></thead>
            <tbody>
            @foreach($analysis['rows'] as $r)
                <tr class="{{ $r['errors'] ? 'table-danger' : '' }}">
                    <td data-l="Ligne">{{ $r['line'] }}</td><td data-l="Matricule">{{ $r['data']['matricule'] }}</td>
                    <td data-l="Nom">{{ trim($r['data']['last_name'].' '.$r['data']['post_name'].' '.$r['data']['first_name']) }}</td>
                    <td data-l="Promotion">{{ $r['data']['promotion_code'] }}</td><td data-l="Statut">{{ $r['status'] ? status_label('enrollment_status', $r['status']) : $r['data']['status'] }}</td>
                    <td data-l="Action">{{ $r['errors'] ? 'Refusée' : ['create' => 'Création', 'update' => 'Mise à jour', 'unchanged' => 'Inchangé', 'skip_existing' => 'Existant non modifié'][$r['action']] ?? '' }}</td>
                    <td data-l="Erreurs" class="small">@foreach($r['errors'] as $e)<div class="text-danger">• {{ $e }}</div>@endforeach @foreach($r['notes'] as $n)<div class="text-muted-2">• {{ $n }}</div>@endforeach</td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
    <form method="post" action="{{ route('imports.students.confirm', $batch) }}" class="card" data-confirm="Confirmer l’import ? Les dossiers et inscriptions seront créés.">@csrf
        <div class="card-header">4. Confirmer</div>
        <div class="card-body">
            @if($s['error'])<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="skip_invalid" value="1" id="si" required><label class="form-check-label" for="si">Importer uniquement les {{ $s['valid'] }} ligne(s) valide(s) et écarter les {{ $s['error'] }} ligne(s) en erreur</label></div>@endif
            @if(can_do('charges.generate'))<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="generate_charges" value="1" id="gc" checked><label class="form-check-label" for="gc">Générer les créances selon les barèmes actifs</label></div>@endif
            @if($async)<p class="small text-muted-2">Fichier volumineux : le traitement s’effectuera en arrière-plan (worker de file d’attente).</p>@endif
            <button class="btn btn-accent" @disabled($s['valid'] === 0)><i class="bi bi-check2-circle"></i> Confirmer l’import</button>
        </div>
    </form>
@endif
@endsection
