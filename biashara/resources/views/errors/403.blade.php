@extends('layouts.app')
@section('title', 'Accès refusé')
@section('content')
<div class="card"><div class="card-body empty py-5">
    <i class="bi bi-shield-lock"></i>
    <h1 class="h4 text-navy">Accès refusé</h1>
    <p>{{ $message ?? "Vous n'avez pas la permission d'effectuer cette opération." }}</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Retour à l'accueil</a>
</div></div>
@endsection
