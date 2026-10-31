@extends('layouts.app')
@section('title', 'Rapport de rapprochement')
@section('content')
@include('partials.head', ['title' => 'Rapport de rapprochement bancaire', 'crumbs' => ['Banque' => route('bank.index')], 'sub' => 'Du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y').' · généré le '.now()->format('d/m/Y H:i'), 'actions' => '<button class="btn btn-light no-print" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer / PDF</button>'])
<div class="card mb-3 no-print"><div class="card-body"><form class="filters" method="get">
    <div><label class="form-label" for="rpa">Compte</label><select id="rpa" name="account" class="form-select"><option value="">Tous</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->display() }}</option>@endforeach</select></div>
    <div><label class="form-label" for="rpf">Du</label><input id="rpf" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
    <div><label class="form-label" for="rpt">Au</label><input id="rpt" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}"></div>
    <div class="acts"><button class="btn btn-primary">Afficher</button></div></form></div></div>
<div class="card mb-3"><div class="card-header">Couverture des relevés</div><div class="card-body">
    @foreach($r['coverage'] as $c)
        <div class="mb-2"><strong>{{ $c['account']->display() }}</strong> — relevés : @forelse($c['batches'] as $b){{ $b->number }} ({{ $b->period_start->format('d/m') }}→{{ $b->period_end->format('d/m/Y') }}){{ !$loop->last ? ', ' : '' }}@empty aucun @endforelse
            <br>@if($c['gaps'])<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Périodes non couvertes : @foreach($c['gaps'] as [$a, $b]){{ \Illuminate\Support\Carbon::parse($a)->format('d/m/Y') }} → {{ \Illuminate\Support\Carbon::parse($b)->format('d/m/Y') }}{{ !$loop->last ? ' ; ' : '' }}@endforeach — les paiements de ces périodes ne peuvent pas être confirmés.</span>@else<span class="text-success">Période entièrement couverte.</span>@endif</div>
    @endforeach
</div></div>
@foreach(['matched' => 'Lignes rapprochées', 'partial' => 'Lignes partiellement rapprochées (écarts)', 'unmatched' => 'Lignes non rapprochées', 'to_review' => 'Extractions restant à vérifier', 'ignored' => 'Lignes écartées (avec motif)'] as $k => $title)
    <div class="card mb-3"><div class="card-header">{{ $title }} ({{ $r[$k]->count() }})</div>
        @if($r[$k]->count())<div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>#</th><th>Date</th><th>Référence</th><th class="num">Montant</th><th class="num">Rapproché</th><th class="num">Écart</th><th>Motif</th></tr></thead><tbody>
            @foreach($r[$k] as $l)<tr><td data-l="#"><a href="{{ route('bank.lines.show', $l) }}">#{{ $l->id }}</a></td><td data-l="Date">{{ $l->operation_date->format('d/m/Y') }}</td><td data-l="Réf.">{{ $l->reference }}</td><td data-l="Montant" class="num">{{ money($l->amount, $l->currency) }}</td><td data-l="Rapproché" class="num">{{ money($l->matched_amount, $l->currency) }}</td><td data-l="Écart" class="num">{{ money($l->remaining(), $l->currency) }}</td><td data-l="Motif" class="small">{{ $l->ignore_reason ?? $l->reliability_note }}</td></tr>@endforeach
        </tbody></table></div>@else<div class="card-body small text-muted-2">Aucune.</div>@endif</div>
@endforeach
<div class="card mb-3"><div class="card-header">Paiements validés non (ou partiellement) confirmés ({{ $r['unconfirmed_payments']->count() }})</div>
    @if($r['unconfirmed_payments']->count())<div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Paiement</th><th>Étudiant</th><th>Date</th><th class="num">Montant</th><th class="num">Confirmé</th></tr></thead><tbody>
    @foreach($r['unconfirmed_payments'] as $p)<tr><td data-l="Paiement"><a href="{{ route('payments.show', $p) }}">{{ $p->number }}</a></td><td data-l="Étudiant">{{ $p->student->matricule }}</td><td data-l="Date">{{ $p->deposit_date->format('d/m/Y') }}</td><td data-l="Montant" class="num">{{ money($p->amount, $p->currency) }}</td><td data-l="Confirmé" class="num">{{ money($p->bank_confirmed_amount, $p->currency) }}</td></tr>@endforeach
    </tbody></table></div>@else<div class="card-body small text-muted-2">Aucun.</div>@endif</div>
<div class="card"><div class="card-header">Anomalies sur paiements ({{ $r['anomalous_payments']->count() }})</div><ul class="list-group list-group-flush small">
    @forelse($r['anomalous_payments'] as $p)<li class="list-group-item"><a href="{{ route('payments.show', $p) }}">{{ $p->number }}</a> ({{ status_label('payment', $p->status) }}) : {{ collect($p->anomalies)->pluck('message')->join(' ; ') }}</li>@empty<li class="list-group-item text-muted-2">Aucune.</li>@endforelse
</ul></div>
@endsection
