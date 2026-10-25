@extends('layouts.app')
@section('title', 'Rapports')
@section('content')
<div class="page-head"><div><h1>Rapports</h1><div class="sub">Filtrables par période, utilisateur, article et catégorie · exports PDF et Excel</div></div></div>
@php $icons = ['sales_detailed' => 'bi-list-ul', 'sales_summary' => 'bi-calendar3', 'sales_by_product' => 'bi-trophy', 'payments' => 'bi-credit-card', 'stock' => 'bi-boxes', 'movements' => 'bi-arrow-left-right', 'purchases' => 'bi-truck', 'returns' => 'bi-arrow-counterclockwise', 'expenses' => 'bi-wallet2', 'inventories' => 'bi-clipboard-check', 'receivables' => 'bi-person-exclamation', 'cash_closures' => 'bi-safe2', 'margins' => 'bi-graph-up-arrow', 'margins_summary' => 'bi-calculator']; @endphp
<div class="tiles">
    @foreach($reports as $key => [$label])
        <a href="{{ route('reports.show', $key) }}" class="tile {{ in_array($key, ['margins_summary']) ? 'navy' : '' }}"><span class="ico"><i class="bi {{ $icons[$key] ?? 'bi-file-text' }}"></i></span><span class="t">{{ $label }}</span></a>
    @endforeach
</div>
<div class="alert alert-light border mt-4 small mb-0">
    <strong>Méthode de valorisation :</strong> coût moyen pondéré (CMP) mobile, recalculé à chaque entrée valorisée ; le coût de sortie est figé dans chaque ligne de vente pour un calcul historique exact des marges.
    La marge commerciale = ventes nettes HT − coût des marchandises vendues ; les dépenses d'exploitation sont présentées séparément et ne sont pas déduites de la marge.
</div>
@endsection
