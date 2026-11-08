@extends('layouts.app')
@section('title', 'Primes')
@section('content')
@include('partials.head', ['title' => 'Droits à primes', 'sub' => 'Montant prévu = taux × part des jours ouvrant droit, puis ajustements ; solde = prévu − mouvements validés. Montants toujours exprimés dans leur devise.', 'actions' => (can_do('primes.generate') ? '<a class="btn btn-primary" href="'.route('primes.generate').'"><i class="bi bi-calculator"></i> Générer une période</a>' : '').(can_do('reports.finance') ? '<a class="btn btn-light" href="'.route('reports.index').'"><i class="bi bi-printer"></i> États</a>' : '')])
<div class="card mb-3"><div class="card-body"><form class="filters" method="get">
    <div><label class="form-label" for="pq">Agent</label><input id="pq" name="q" value="{{ request('q') }}" class="form-control" placeholder="Matricule ou nom"></div>
    <div><label class="form-label" for="pt">Type</label><select id="pt" name="type" class="form-select"><option value="">Tous</option>@foreach($types as $t)<option value="{{ $t->id }}" @selected(request('type') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
    <div><label class="form-label" for="pp">Période</label><select id="pp" name="period" class="form-select"><option value="">Toutes</option>@foreach($periods as $p)<option @selected(request('period') === $p)>{{ $p }}</option>@endforeach</select></div>
    <div><label class="form-label" for="pu">Unité</label><select id="pu" name="unit" class="form-select"><option value="">Toutes</option>@foreach($units as $un)<option value="{{ $un->id }}" @selected(request('unit') == $un->id)>{{ $un->name }}</option>@endforeach</select></div>
    <div><label class="form-label" for="pb">Solde</label><select id="pb" name="balance" class="form-select"><option value="">Tous</option><option value="due" @selected(request('balance') === 'due')>Reste à payer</option></select></div>
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button>
</form></div></div>
<div class="card">
@if($items->count())<div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Période</th><th>Agent</th><th>Prime</th><th class="num">Taux</th><th class="num">Prévu</th><th class="num">Payé</th><th class="num">Acomptes</th><th class="num">Solde</th><th>Statut</th></tr></thead><tbody>
@foreach($items as $e)<tr>
    <td data-l="Période"><a href="{{ route('primes.show', $e) }}" class="fw-600">{{ $e->period_label }}</a></td><td data-l="Agent">{{ $e->employee->fullName() }}<br><span class="small text-muted-2">{{ $e->employee->matricule }}</span></td><td data-l="Prime">{{ $e->primeType?->name }}</td>
    <td data-l="Taux" class="num">{{ money($e->base_amount, $e->currency) }}</td><td data-l="Prévu" class="num">{{ money($e->netAmount(), $e->currency) }}</td><td data-l="Payé" class="num">{{ money($e->paidAmount(), $e->currency) }}</td><td data-l="Acomptes" class="num">{{ money($e->advancesAmount(), $e->currency) }}</td>
    <td data-l="Solde" class="num fw-600 {{ \App\Support\Money::isNegative($e->balance()) ? 'text-danger' : '' }}">{{ money($e->balance(), $e->currency) }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('entitlement', $e->status) !!}</td>
</tr>@endforeach</tbody></table></div>
<div class="card-body border-top">{{ $items->links() }}</div>
@else <div class="card-body">@include('partials.empty', ['icon' => 'bi-cash-coin', 'text' => 'Aucun droit à prime pour ces critères.'])</div> @endif
</div>
@endsection
