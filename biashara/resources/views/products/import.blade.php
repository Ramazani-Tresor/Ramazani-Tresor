@extends('layouts.app')
@section('title', 'Importer des articles')
@section('content')
<div class="page-head"><div><h1>Importer des articles</h1><div class="sub">Fichier Excel (.xlsx) ou CSV (séparateur « ; » ou « , »)</div></div>
    <a href="{{ route('products.import.template') }}" class="btn btn-light"><i class="bi bi-download me-1"></i>Télécharger le modèle</a></div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <form method="post" enctype="multipart/form-data" action="{{ route('products.import.run') }}" data-allow-resubmit>
                @csrf
                <div class="mb-3"><label class="form-label">Fichier</label><input type="file" name="file" class="form-control" accept=".xlsx,.csv,.txt" required></div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-light" name="dry_run" value="1"><i class="bi bi-search me-1"></i>Vérifier sans importer</button>
                    <button class="btn btn-primary" data-confirm="Lancer l'import ? Les articles existants (même référence) seront mis à jour, sans modification de leur stock."><i class="bi bi-upload me-1"></i>Importer</button>
                </div>
            </form>
            @if(session('import_errors'))
                <div class="alert alert-danger mt-3 mb-0"><div class="fw-600 mb-1">Lignes refusées :</div>
                    <ul class="mb-0 small">@foreach(session('import_errors') as $line => $err)<li>Ligne {{ $line }} : {{ $err }}</li>@endforeach</ul></div>
            @endif
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Règles d'import contrôlé</div><div class="card-body small">
            <ul class="mb-0">
                <li>Colonnes obligatoires : <code>reference</code>, <code>designation</code>, <code>categorie</code>, <code>prix_vente</code>.</li>
                <li>Colonnes facultatives : <code>type</code> (produit/service), <code>unite</code>, <code>prix_achat</code>, <code>seuil_alerte</code>, <code>code_barres</code>, <code>stock_initial</code>, <code>description</code>.</li>
                <li>Le fichier est validé <strong>en entier</strong> : si une ligne est invalide, <strong>rien</strong> n'est importé.</li>
                <li>Référence existante : les informations sont mises à jour, le stock n'est jamais modifié par l'import.</li>
                <li>Nouvel article avec <code>stock_initial</code> : un mouvement « Stock initial » est créé.</li>
                <li>Catégories inexistantes créées automatiquement. 5 000 lignes maximum.</li>
            </ul>
        </div></div>
    </div>
</div>
@endsection
