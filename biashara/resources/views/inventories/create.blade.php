@extends('layouts.app')
@section('title', 'Ouvrir un inventaire')
@section('content')
<div class="page-head"><h1>Ouvrir un inventaire</h1></div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><form class="card-body" method="post" action="{{ route('inventories.store') }}">@csrf
        <div class="mb-3"><label class="form-label">Périmètre</label><select name="category_id" class="form-select"><option value="">Tous les produits actifs</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" maxlength="1000"></textarea></div>
        <button class="btn btn-primary">Ouvrir la session d'inventaire</button>
    </form></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Procédure</div><div class="card-body small">
        <ol class="mb-0">
            <li>Les ventes <strong>ne sont pas bloquées</strong> pendant l'inventaire.</li>
            <li>Comptez un rayon, puis saisissez immédiatement les quantités : le <strong>stock théorique est figé au moment de la saisie</strong> de chaque article.</li>
            <li>L'écart (compté − théorique au relevé) est justifié pour chaque article concerné.</li>
            <li>À la validation par un responsable, l'écart est appliqué au stock courant par un mouvement « Ajustement d'inventaire » : les ventes et entrées intervenues après le relevé restent prises en compte.</li>
            <li>Évitez de compter un article pendant qu'il est en cours d'encaissement (panier ouvert).</li>
        </ol>
    </div></div></div>
</div>
@endsection
