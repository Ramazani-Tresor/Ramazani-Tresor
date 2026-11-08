@extends('layouts.app')
@section('title', 'Accès refusé')
@section('content')
<div class="card"><div class="empty py-5"><i class="bi bi-shield-lock"></i><h1 class="h4 text-navy">Accès refusé</h1><p>{{ $message ?? 'Cette opération ou ces données sont hors de vos habilitations.' }}</p><a href="{{ route('home') }}" class="btn btn-primary">Retour</a></div></div>
@endsection
