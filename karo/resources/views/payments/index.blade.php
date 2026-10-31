@extends('layouts.app')
@section('title', 'Paiements')
@section('content')
@include('partials.head', ['title' => 'Paiements', 'sub' => $items->total().' paiement(s)'])
<div class="card">
    <div class="card-body border-bottom">@include('partials.filters', ['show' => ['q', 'year', 'faculty', 'promotion', 'account', 'currency', 'period', 'status'], 'statuses' => \App\Support\Labels::STATUSES['payment'] + \App\Support\Labels::STATUSES['bank']])</div>
    @include('payments._table')
    <div class="card-body">{{ $items->links() }}</div>
</div>
@endsection
