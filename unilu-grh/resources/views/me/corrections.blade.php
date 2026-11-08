@extends('layouts.app')
@section('title', 'Mes demandes')
@section('content')
@include('partials.head', ['title' => 'Mes demandes de correction', 'sub' => 'Signalez une information inexacte ; le service du personnel la vérifie et vous notifie sa décision.'])
@include('me._tabs')
<div class="row g-3">
<div class="col-lg-5"><div class="card"><div class="card-header">Nouvelle demande</div><div class="card-body"><form method="post" action="{{ route('me.corrections.store') }}" enctype="multipart/form-data" class="row g-2">@csrf
    @include('partials.field', ['name' => 'field', 'label' => 'Rubrique', 'type' => 'select', 'required' => true, 'options' => $fields, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'requested_value', 'label' => 'Valeur correcte', 'type' => 'textarea', 'required' => true, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'justification', 'label' => 'Justification', 'type' => 'textarea', 'required' => true, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'document', 'label' => 'Pièce justificative (facultative)', 'type' => 'file', 'col' => 'col-12'])
    <div class="col-12"><button class="btn btn-primary">Envoyer</button></div>
</form></div></div></div>
<div class="col-lg-7"><div class="card"><div class="card-header">Suivi de mes demandes</div>
@if($requests->count())<ul class="list-group list-group-flush">@foreach($requests as $r)<li class="list-group-item"><div class="d-flex justify-content-between flex-wrap gap-1"><b>{{ $fields[$r->field] ?? $r->field }}</b>{!! \App\Support\Labels::badge('correction', $r->status) !!}</div><div class="small">Demandé : {{ $r->requested_value }} · le {{ $r->created_at->format('d/m/Y') }}</div>@if($r->response)<div class="small text-muted-2">Réponse : {{ $r->response }}</div>@endif</li>@endforeach</ul>@else <div class="card-body small text-muted-2">Aucune demande.</div> @endif
</div></div>
</div>
@endsection
