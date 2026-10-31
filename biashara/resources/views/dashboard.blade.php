@extends('layouts.app')
@section('title', 'Tableau de bord')
@section('content')
<div class="page-head">
    <div>
        <h1>Tableau de bord</h1>
        <div class="sub">Période du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}{{ $restricted ? ' · vos ventes uniquement' : '' }} · généré à {{ now()->format('H:i') }}</div>
    </div>
    <form class="filters" method="get">
        @include('partials.period')
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Afficher</button>
    </form>
</div>

@php
    $tip = fn ($k) => '<i class="bi bi-info-circle info-tip" data-bs-toggle="tooltip" title="'.e($definitions[$k] ?? '').'"></i>';
@endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card kpi accent"><div class="label">Ventes du jour</div><div class="value">{{ money($today['revenue_ttc']) }}</div><div class="hint">{{ $today['sales_count'] }} vente(s) · panier moyen {{ money($today['average_ticket']) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi"><div class="label">Chiffre d'affaires période {!! $tip('revenue_ttc') !!}</div><div class="value">{{ money($period['revenue_ttc']) }}</div><div class="hint">{{ $period['sales_count'] }} vente(s), retours {{ money($period['returns_ttc']) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi"><div class="label">Encaissements {!! $tip('net_collected') !!}</div><div class="value">{{ money($period['net_collected']) }}</div><div class="hint">reçus {{ money($period['collected']) }} − remboursés {{ money($period['refunds']) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi"><div class="label">Solde de caisse {!! $tip('cash_balance') !!}</div><div class="value">{{ money($period['cash_balance']) }}</div><div class="hint">espèces théoriques des caisses ouvertes</div></div></div>
    @if($showFinancial)
        <div class="col-6 col-xl-3"><div class="card kpi"><div class="label">Marge commerciale {!! $tip('margin') !!}</div><div class="value text-success">{{ money($period['margin']) }}</div><div class="hint">taux {{ str_replace('.', ',', $period['margin_rate']) }} % · CMV {{ money($period['cogs']) }}</div></div></div>
        <div class="col-6 col-xl-3"><div class="card kpi"><div class="label">Dépenses {!! $tip('expenses') !!}</div><div class="value text-danger">{{ money($period['expenses']) }}</div><div class="hint">pertes de stock {{ money($period['stock_losses']) }}</div></div></div>
        <div class="col-6 col-xl-3"><div class="card kpi"><div class="label">Résultat estimatif {!! $tip('estimated_result') !!}</div><div class="value {{ \App\Support\Money::isNegative($period['estimated_result']) ? 'text-danger' : '' }}">{{ money($period['estimated_result']) }}</div><div class="hint">indicatif, non comptable</div></div></div>
    @endif
    <div class="col-6 col-xl-3"><div class="card kpi"><div class="label">Créances clients {!! $tip('receivables') !!}</div><div class="value text-warning">{{ money($period['receivables']) }}</div><div class="hint"><a href="{{ route('customers.index', ['debt' => 1]) }}">voir les clients débiteurs</a></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">Évolution des ventes et des dépenses</div>
            <div class="card-body"><canvas id="chart" height="120" aria-label="Graphique des ventes et dépenses par jour" role="img"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Alertes de stock</span><span class="status status-danger">{{ $outOfStock }} en rupture</span></div>
            <div class="list-group list-group-flush">
                @forelse($lowStock as $p)
                    <a href="{{ route('products.show', $p) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span>{{ $p->name }}</span>
                        <span class="status {{ (float) $p->stock_quantity <= 0 ? 'status-danger' : 'status-warn' }}">{{ qty($p->stock_quantity) }} / seuil {{ qty($p->alert_threshold) }}</span>
                    </a>
                @empty
                    <div class="empty"><i class="bi bi-check-circle"></i>Aucun article sous le seuil.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">Articles les plus vendus</div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Article</th><th class="num">Qté nette</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse($topProducts as $t)
                    <tr><td>{{ $t->name }}</td><td class="num">{{ qty($t->qty) }}</td><td class="num">{{ money($t->total) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">Aucune vente sur la période.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">Dernières transactions</div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>N°</th><th>Heure</th><th>Client</th><th>Vendeur</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                @forelse($latestSales as $s)
                    <tr>
                        <td><a href="{{ route('sales.show', $s) }}">{{ $s->number }}</a></td>
                        <td>{{ $s->sold_at->format('d/m H:i') }}</td>
                        <td>{{ $s->customer?->name ?? 'Passage' }}</td>
                        <td>{{ $s->user->name }}</td>
                        <td class="num">{{ money($s->total) }}</td>
                        <td>@include('sales._status', ['sale' => $s])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucune vente.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    (function () {
        const data = @json($chart);
        const fmt = (v) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v);
        new Chart(document.getElementById('chart'), {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    { label: 'Ventes nettes TTC', data: data.sales, backgroundColor: '#1E6FD9', borderRadius: 4 },
                    { label: 'Dépenses validées', data: data.expenses, type: 'line', borderColor: '#C0392B', backgroundColor: '#C0392B', tension: .25, pointRadius: 2 },
                ],
            },
            options: { responsive: true, plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ' : ' + fmt(c.raw) + ' {{ setting('currency_symbol') }}' } } }, scales: { y: { ticks: { callback: fmt } } } },
        });
    })();
</script>
@endpush
