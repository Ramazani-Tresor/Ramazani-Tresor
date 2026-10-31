@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<div class="page-head"><div><h1>Clients et créances</h1><div class="sub">Total des créances : <strong class="text-danger">{{ money($totalDue) }}</strong></div></div>
    @if(can_do('customers.manage'))<a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nouveau client</a>@endif</div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        <div><input type="search" name="q" class="form-control" placeholder="Nom, téléphone ou code" value="{{ request('q') }}"></div>
        <div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="debt" value="1" id="debt" @checked(request('debt'))><label class="form-check-label" for="debt">Uniquement les débiteurs</label></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Code</th><th>Nom</th><th>Téléphone</th><th class="num">Ventes</th><th class="num">Solde dû</th><th></th></tr></thead>
        <tbody>
        @forelse($customers as $c)
            <tr><td class="small text-muted-2">{{ $c->code }}</td><td><a href="{{ route('customers.show', $c) }}" class="fw-600">{{ $c->name }}</a>@unless($c->is_active) <span class="status status-muted">Inactif</span>@endunless</td><td>{{ $c->phone }}</td><td class="num">{{ $c->sales_count }}</td>
                <td class="num {{ (float) $c->balance > 0 ? 'text-danger fw-600' : '' }}">{{ money($c->balance ?? 0) }}</td>
                <td class="text-end"><a href="{{ route('customers.statement', $c) }}" class="btn btn-sm btn-light" title="Relevé"><i class="bi bi-file-text"></i></a></td></tr>
        @empty
            <tr><td colspan="6" class="empty"><i class="bi bi-people"></i>Aucun client.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $customers->links() }}</div>
</div>
@endsection
