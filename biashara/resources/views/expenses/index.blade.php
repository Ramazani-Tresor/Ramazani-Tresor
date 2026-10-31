@extends('layouts.app')
@section('title', 'Dépenses')
@section('content')
<div class="page-head"><div><h1>Dépenses</h1><div class="sub">Total validé sur la période : <strong>{{ money($approvedTotal) }}</strong></div></div>
    @if(can_do('expenses.create'))<a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvelle dépense</a>@endif</div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        @include('partials.period')
        <div><label class="form-label small mb-1">Recherche</label><input type="search" name="q" class="form-control" placeholder="Bénéficiaire, motif, n°" value="{{ request('q') }}"></div>
        <div><label class="form-label small mb-1">Catégorie</label><select name="category_id" class="form-select"><option value="">Toutes</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-1">Statut</label><select name="status" class="form-select"><option value="">Tous</option>@foreach(\App\Models\Expense::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>N°</th><th>Date</th><th>Catégorie</th><th>Bénéficiaire</th><th>Motif</th><th>Mode</th><th>Statut</th><th class="num">Montant</th><th></th></tr></thead>
        <tbody>
        @forelse($expenses as $e)
            <tr><td><a href="{{ route('expenses.show', $e) }}" class="fw-600">{{ $e->number }}</a></td><td>{{ $e->expense_date->format('d/m/Y') }}</td><td>{{ $e->category->name }}</td><td>{{ $e->beneficiary }}</td><td>{{ \Illuminate\Support\Str::limit($e->reason, 40) }}</td>
                <td>{{ $e->method->name }}@if($e->paid_from_cash) <i class="bi bi-cash-coin text-muted-2" title="Payée depuis la caisse"></i>@endif</td>
                <td>@include('expenses._status', ['expense' => $e])</td><td class="num">{{ money($e->amount) }}</td>
                <td class="text-end">@if($e->attachment_path)<i class="bi bi-paperclip" title="Justificatif"></i>@endif</td></tr>
        @empty
            <tr><td colspan="9" class="empty"><i class="bi bi-wallet2"></i>Aucune dépense sur la période.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $expenses->links() }}</div>
</div>
@endsection
