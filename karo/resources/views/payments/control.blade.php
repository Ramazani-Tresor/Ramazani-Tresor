@extends('layouts.app')
@section('title', 'Contrôle et validation')
@section('content')
@include('partials.head', ['title' => 'Contrôle et validation', 'sub' => 'Circuit : saisie → contrôle du justificatif → validation interne. Les bordereaux avec anomalie sont présentés en premier. Vous ne pouvez ni contrôler ni valider vos propres saisies.'])
<div class="card">
    <div class="card-body border-bottom">@include('partials.filters', ['show' => ['year', 'faculty', 'promotion', 'status'], 'statuses' => ['submitted' => 'À contrôler', 'checked' => 'À valider']])</div>
    @include('payments._table', ['emptyText' => 'Aucun bordereau en attente. 🎉'])
    <div class="card-body">{{ $items->links() }}</div>
</div>
@endsection
