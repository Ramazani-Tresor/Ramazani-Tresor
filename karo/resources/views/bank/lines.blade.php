@extends('layouts.app')
@section('title', 'Lignes de relevé')
@section('content')
@include('partials.head', ['title' => 'Lignes de relevé', 'crumbs' => ['Banque' => route('bank.index')]])
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        <div><label class="form-label" for="lq">Référence / libellé</label><input id="lq" type="search" name="q" class="form-control" value="{{ request('q') }}"></div>
        <div><label class="form-label" for="la">Compte</label><select id="la" name="account" class="form-select"><option value="">Tous</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->display() }}</option>@endforeach</select></div>
        <div><label class="form-label" for="ls">État</label><select id="ls" name="status" class="form-select"><option value="">Tous</option>@foreach(\App\Support\Labels::STATUSES['line_match'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div><label class="form-label" for="lr">Vérification</label><select id="lr" name="review" class="form-select"><option value="">Toutes</option>@foreach(\App\Support\Labels::STATUSES['line_review'] as $k => $v)<option value="{{ $k }}" @selected(request('review') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="acts"><button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button></div>
    </form></div>
    @include('bank._lines')
    <div class="card-body">{{ $lines->links() }}</div>
</div>
@endsection
