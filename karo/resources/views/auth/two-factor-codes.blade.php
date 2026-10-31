@extends('layouts.guest')
@section('title', 'Codes de récupération')
@section('content')
<h2 class="h4 fw-bold text-navy">Double authentification activée</h2>
<div class="alert alert-warning small">Conservez ces <strong>codes de récupération</strong> en lieu sûr (imprimés, hors du poste). Chacun ne sert qu’une fois. Ils ne seront plus affichés.</div>
<ul class="list-unstyled font-monospace fs-5 text-center">@foreach($codes as $c)<li>{{ $c }}</li>@endforeach</ul>
<button class="btn btn-light w-100 mb-2" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
<a href="{{ route('home') }}" class="btn btn-primary w-100">Continuer</a>
@endsection
