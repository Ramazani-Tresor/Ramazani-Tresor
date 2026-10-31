@extends('layouts.app')
@section('title', 'Inventaire '.$inventory->number)
@section('content')
<div class="page-head">
    <div><h1>Inventaire {{ $inventory->number }}</h1><div class="sub">{{ $inventory->category?->name ?? 'Tous les articles' }} · ouvert par {{ $inventory->opener->name }} le {{ $inventory->opened_at->format('d/m/Y H:i') }}
        @if($inventory->validator) · validé par {{ $inventory->validator->name }} le {{ $inventory->validated_at->format('d/m/Y H:i') }}@endif</div></div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('inventories.export', $inventory) }}" class="btn btn-light"><i class="bi bi-file-earmark-excel me-1"></i>Exporter</a>
        @if($inventory->isOpen() && can_do('inventories.validate'))
            <form method="post" action="{{ route('inventories.cancel', $inventory) }}" data-confirm="Annuler cet inventaire ? Aucun ajustement ne sera généré.">@csrf<button class="btn btn-outline-danger">Annuler</button></form>
            <form method="post" action="{{ route('inventories.validate', $inventory) }}" data-confirm="Valider l'inventaire ? Les écarts seront appliqués au stock par des mouvements d'ajustement.">@csrf<button class="btn btn-primary"><i class="bi bi-check2-all me-1"></i>Valider l'inventaire</button></form>
        @endif
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Articles</div><div class="value">{{ $stats['total'] }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Comptés</div><div class="value">{{ $stats['counted'] }}</div><div class="hint">{{ $stats['total'] ? round($stats['counted'] * 100 / $stats['total']) : 0 }} %</div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Avec écart</div><div class="value text-warning">{{ $stats['gaps'] }}</div></div></div>
    @if($showCost)<div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Valeur des écarts</div><div class="value {{ (float) $stats['value'] < 0 ? 'text-danger' : '' }}">{{ money($stats['value']) }}</div></div></div>@endif
</div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        <div><input type="search" name="q" class="form-control" placeholder="Rechercher un article" value="{{ request('q') }}"></div>
        <div><select name="filter" class="form-select"><option value="">Tous</option><option value="pending" @selected(request('filter') === 'pending')>Non comptés</option><option value="gaps" @selected(request('filter') === 'gaps')>Avec écart</option></select></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i></button>
    </form></div>
    <form method="post" action="{{ route('inventories.count', $inventory) }}" data-allow-resubmit>@csrf
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Article</th><th class="num">Stock actuel</th><th class="num">Théorique au relevé</th><th style="width:140px">Compté</th><th class="num">Écart</th><th style="min-width:220px">Justification</th><th>Relevé</th></tr></thead>
        <tbody>
        @forelse($items as $i)
            <tr>
                <td>{{ $i->product->name }}<div class="small text-muted-2">{{ $i->product->reference }}</div></td>
                <td class="num">{{ qty($i->product->stock_quantity) }} {{ $i->product->unit->symbol }}</td>
                <td class="num">{{ $i->theoretical_quantity !== null ? qty($i->theoretical_quantity) : '—' }}</td>
                <td><input name="counts[{{ $i->id }}][counted_quantity]" class="form-control form-control-sm text-end" inputmode="decimal" value="{{ $i->counted_quantity !== null ? (string) (float) $i->counted_quantity : '' }}" @disabled(!$inventory->isOpen() || !can_do('inventories.count'))></td>
                <td class="num {{ (float) $i->difference < 0 ? 'text-danger' : ((float) $i->difference > 0 ? 'text-success' : '') }}">{{ $i->difference !== null ? qty($i->difference) : '—' }}</td>
                <td><input name="counts[{{ $i->id }}][justification]" class="form-control form-control-sm" value="{{ $i->justification }}" maxlength="255" @disabled(!$inventory->isOpen() || !can_do('inventories.count'))></td>
                <td class="small text-muted-2">{{ $i->counter?->name }}<br>{{ $i->counted_at?->format('d/m H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Aucun article.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body d-flex justify-content-between flex-wrap gap-2">
        {{ $items->links() }}
        @if($inventory->isOpen() && can_do('inventories.count'))<button class="btn btn-primary"><i class="bi bi-save me-1"></i>Enregistrer les comptages de cette page</button>@endif
    </div>
    </form>
</div>
@endsection
