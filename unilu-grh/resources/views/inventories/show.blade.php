@extends('layouts.app')
@section('title', $inventory->reference)
@php $open = $inventory->status === 'open'; @endphp
@section('content')
@include('partials.head', ['title' => $inventory->label, 'crumbs' => ['Inventaires' => route('inventories.index')], 'sub' => e($inventory->reference).' · '.e($inventory->orgUnit?->name ?? 'Toute l’université').' · '.$lines->count().' / '.$assets->count().' bien(s) constaté(s) · '.\App\Support\Labels::badge('inventory', $inventory->status), 'actions' => $open ? '<form method="post" action="'.route('inventories.close', $inventory).'" data-confirm="Clôturer l’inventaire ? Les états constatés seront appliqués aux biens.">'.csrf_field().'<button class="btn btn-primary"><i class="bi bi-lock"></i> Clôturer</button></form>' : ''])
<div class="card">
<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Code</th><th>Désignation</th><th>État enregistré</th><th>Constat</th>@if($open)<th>Saisie du constat</th>@endif</tr></thead><tbody>
@foreach($assets as $a)@php $l = $lines[$a->id] ?? null; @endphp<tr>
    <td data-l="Code"><a href="{{ route('assets.show', $a) }}">{{ $a->code }}</a></td><td data-l="Désignation">{{ $a->designation }} <span class="mini text-muted-2">{{ $a->location }}</span></td><td data-l="État">{{ \App\Support\Labels::status('asset_condition', $a->condition) }}</td>
    <td data-l="Constat">@if($l){!! $l->found ? '<span class="badge-st st-ok">Présent</span>' : '<span class="badge-st st-danger">Non retrouvé</span>' !!} {{ $l->observed_condition ? \App\Support\Labels::status('asset_condition', $l->observed_condition) : '' }} {{ $l->observed_location }}@else<span class="small text-muted-2">À constater</span>@endif</td>
    @if($open)<td><form method="post" action="{{ route('inventories.check', $inventory) }}" class="d-flex flex-wrap gap-1">@csrf<input type="hidden" name="asset_id" value="{{ $a->id }}">
        <select name="found" class="form-select form-select-sm" style="width:auto" aria-label="Présence"><option value="1">Présent</option><option value="0" @selected($l && ! $l->found)>Non retrouvé</option></select>
        <select name="observed_condition" class="form-select form-select-sm" style="width:auto" aria-label="État constaté"><option value="">État inchangé</option>@foreach(\App\Support\Labels::options('asset_condition') as $k => $v)<option value="{{ $k }}" @selected($l?->observed_condition === $k)>{{ $v }}</option>@endforeach</select>
        <input name="observed_location" class="form-control form-control-sm" style="width:140px" placeholder="Localisation" value="{{ $l?->observed_location }}" aria-label="Localisation constatée">
        <button class="btn btn-sm btn-light">OK</button></form></td>@endif
</tr>@endforeach
</tbody></table></div>
</div>
@endsection
