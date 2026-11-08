@extends('layouts.app')
@section('title', 'Import Excel des étudiants')
@section('content')
@include('partials.head', ['title' => 'Import collectif des étudiants', 'sub' => 'Excel (.xlsx) ou CSV · par faculté et promotion · prévisualisation obligatoire avant toute écriture',
    'actions' => '<a class="btn btn-light" href="'.route('imports.students.template').'"><i class="bi bi-download"></i> Modèle Excel</a>'])
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-header">1. Déposer le fichier</div>
        <form class="card-body" method="post" enctype="multipart/form-data" action="{{ route('imports.students.upload') }}">@csrf
            <div class="mb-3"><label class="form-label" for="iy">Année académique des inscriptions</label><select id="iy" name="academic_year_id" class="form-select" required>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
            <div class="mb-3"><label class="form-label" for="if">Fichier (10 Mo, 20 000 lignes max.)</label><input id="if" type="file" name="file" class="form-control" accept=".xlsx,.csv" required></div>
            <button class="btn btn-primary"><i class="bi bi-upload"></i> Déposer</button>
            <p class="small text-muted-2 mt-3 mb-0">Le fichier est conservé (dossier privé) avec son empreinte. Son contenu est traité comme des données : aucune formule ni macro n’est exécutée.</p>
        </form></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header">Derniers imports</div>
        <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Lot</th><th>Fichier</th><th>Lignes</th><th>Résultat</th><th>Statut</th></tr></thead><tbody>
        @forelse($batches as $b)<tr><td data-l="Lot"><a href="{{ route('imports.students.show', $b) }}">{{ $b->number }}</a></td><td data-l="Fichier" class="small">{{ \Illuminate\Support\Str::limit($b->original_name, 30) }}<br>{{ $b->creator->name }}, {{ $b->created_at->format('d/m/Y H:i') }}</td><td data-l="Lignes">{{ $b->rows_total }}</td><td data-l="Résultat" class="small">{{ $b->rows_created }} créés · {{ $b->rows_updated }} m. à j. · {{ $b->rows_skipped }} ignorés</td><td data-l="Statut">{{ ['uploaded' => 'Déposé', 'previewed' => 'Prévisualisé', 'processing' => 'En cours', 'completed' => 'Terminé', 'failed' => 'Échec', 'cancelled' => 'Annulé'][$b->status] }}</td></tr>
        @empty<tr><td colspan="5">@include('partials.empty', ['text' => 'Aucun import.'])</td></tr>@endforelse
        </tbody></table></div></div></div>
</div>
@endsection
