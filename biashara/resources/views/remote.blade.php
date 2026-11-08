@extends('layouts.app')
@section('title', 'Consultation à distance')
@section('content')
<div class="page-head">
    <div>
        <h1><i class="bi bi-broadcast me-1"></i>Consultation à distance</h1>
        <div class="sub">Données du serveur générées le <strong>{{ $generatedAt->format('d/m/Y à H:i:s') }}</strong>.
            Dernière synchronisation reçue d'un appareil : <strong>{{ $lastSync ? \Illuminate\Support\Carbon::parse($lastSync)->format('d/m/Y H:i') : 'aucune' }}</strong>.</div>
    </div>
    <form class="filters" method="get">@include('partials.period')<button class="btn btn-primary">Afficher</button></form>
</div>
<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Les ventes réalisées hors ligne n'apparaissent qu'après la synchronisation de l'appareil concerné. Vérifiez la colonne « dernière synchro » ci-dessous avant de conclure.
    @if($pendingConflicts) <a href="{{ route('sync.index') }}" class="fw-600">{{ $pendingConflicts }} vente(s) en conflit à traiter.</a>@endif
</div>
<div class="row g-3 mb-3">
    @foreach([['Chiffre d\'affaires', 'revenue_ttc', ''], ['Encaissements nets', 'net_collected', ''], ['Marge commerciale', 'margin', 'text-success'], ['Dépenses', 'expenses', 'text-danger'], ['Résultat estimatif', 'estimated_result', ''], ['Créances', 'receivables', 'text-warning'], ['Espèces en caisses ouvertes', 'cash_balance', '']] as [$l, $k, $c])
        <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">{{ $l }}</div><div class="value {{ $c }}">{{ money($metrics[$k]) }}</div></div></div>
    @endforeach
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Ventes</div><div class="value">{{ $metrics['sales_count'] }}</div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Caisses ouvertes</div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Caissier</th><th>Depuis</th><th class="num">Espèces</th><th class="num">Électronique</th></tr></thead><tbody>
        @forelse($sessions as $s)<tr><td><a href="{{ route('cash.show', $s['session']) }}">{{ $s['session']->user->name }}</a></td><td>{{ $s['session']->opened_at->format('d/m H:i') }}</td><td class="num">{{ money($s['expected']) }}</td><td class="num">{{ money($s['electronic']) }}</td></tr>
        @empty<tr><td colspan="4" class="empty">Aucune caisse ouverte.</td></tr>@endforelse
        </tbody></table></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Appareils</div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Appareil</th><th>Utilisateur</th><th>Dernière activité</th><th>Dernière synchro</th></tr></thead><tbody>
        @forelse($devices as $d)<tr><td class="small">{{ \Illuminate\Support\Str::limit($d->label ?? $d->device_id, 18) }}</td><td>{{ $d->user?->name }}</td><td>{{ $d->last_seen_at?->diffForHumans() }}</td><td>{{ $d->last_sync_at?->format('d/m H:i') ?? '—' }}</td></tr>
        @empty<tr><td colspan="4" class="empty">Aucun appareil enregistré.</td></tr>@endforelse
        </tbody></table></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Dernières ventes</div>
        <div class="table-responsive"><table class="table"><tbody>
        @foreach($latestSales as $s)<tr><td><a href="{{ route('sales.show', $s) }}">{{ $s->number }}</a></td><td>{{ $s->sold_at->format('d/m H:i') }}</td><td>{{ $s->user->name }}</td><td class="num">{{ money($s->total) }}</td><td>@include('sales._status', ['sale' => $s])</td></tr>@endforeach
        </tbody></table></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Stock bas et dernières dépenses</div>
        <ul class="list-group list-group-flush">
            @foreach($lowStock as $p)<li class="list-group-item d-flex justify-content-between"><span>{{ $p->name }}</span><span class="status status-warn">{{ qty($p->stock_quantity) }}</span></li>@endforeach
            @foreach($latestExpenses as $e)<li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-wallet2 me-1"></i>{{ $e->reason }} <small class="text-muted-2">({{ \App\Models\Expense::STATUSES[$e->status] }})</small></span><span class="money">{{ money($e->amount) }}</span></li>@endforeach
        </ul></div></div>
</div>
<div class="d-flex flex-wrap gap-2 mt-3">
    @if(can_do('reports.view'))<a href="{{ route('reports.index') }}" class="btn btn-light"><i class="bi bi-bar-chart-line me-1"></i>Rapports</a>@endif
    @if(can_do('stock.view'))<a href="{{ route('stock.index') }}" class="btn btn-light"><i class="bi bi-boxes me-1"></i>Stocks</a>@endif
    @if(can_do('cash.view_all'))<a href="{{ route('cash.sessions') }}" class="btn btn-light"><i class="bi bi-safe2 me-1"></i>Caisses</a>@endif
    @if(can_do('sales.view'))<a href="{{ route('sales.index') }}" class="btn btn-light"><i class="bi bi-receipt me-1"></i>Ventes</a>@endif
    @if(can_do('expenses.view'))<a href="{{ route('expenses.index') }}" class="btn btn-light"><i class="bi bi-wallet2 me-1"></i>Dépenses</a>@endif
</div>
@endsection
