@extends('layouts.app')
@section('title', 'Paramétrage')
@section('content')
@include('partials.head', ['title' => 'Paramétrage', 'sub' => 'Référentiels et règles modifiables sans intervention informatique ; chaque changement est journalisé. Rien n’est supprimé : les valeurs obsolètes sont désactivées.'])
<div class="row g-3">
<div class="col-lg-4">@include('settings._nav')</div>
<div class="col-lg-8"><form method="post" action="{{ route('settings.update') }}" class="card"><div class="card-header">Institution, en-têtes des éditions et règles générales</div><div class="card-body row g-3">@csrf @method('PUT')
    @foreach($fields as $k => [$l, $t])
        @include('partials.field', ['name' => $k, 'label' => $l, 'type' => $t === 'bool' ? 'bool' : ($t === 'number' ? 'number' : ($t === 'email' ? 'email' : 'text')), 'value' => setting($k), 'col' => $t === 'bool' ? 'col-12' : 'col-md-6'])
    @endforeach
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Enregistrer</button></div></form></div>
</div>
@endsection
