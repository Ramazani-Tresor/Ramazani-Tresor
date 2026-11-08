@extends('layouts.app')
@section('title', 'Structure et postes')
@section('content')
@include('partials.head', ['title' => 'Structure organisationnelle', 'sub' => 'Rectorat, vice-rectorats, secrétariats généraux, facultés, départements, centres, bibliothèques et services — avec effectifs (affectations principales actives) et postes.'])
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card kpi accent"><div class="l">Unités</div><div class="v">{{ $units->where('is_active', true)->count() }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi"><div class="l">Agents affectés</div><div class="v">{{ $counts->sum() }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi"><div class="l">Postes occupés</div><div class="v">{{ $stats['occupied'] }} / {{ $stats['seats'] }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi warn-k"><div class="l">Postes vacants</div><div class="v">{{ $stats['seats'] - $stats['occupied'] }}</div></div></div>
</div>
<div class="row g-3">
<div class="col-lg-7"><div class="card"><div class="card-header">Organigramme</div><div class="card-body">
    @php $render = function ($parent) use (&$render, $tree, $counts) { $out = ''; foreach ($tree[$parent] ?? [] as $u) { $out .= '<li><a href="'.route('structure.show', $u).'" class="'.($u->is_active ? '' : 'text-muted-2 text-decoration-line-through').'">'.e($u->name).'</a> <span class="mini text-muted-2">'.e($u->typeLabel()).'</span> '.(($counts[$u->id] ?? 0) ? '<span class="badge text-bg-light border">'.$counts[$u->id].'</span>' : '').(isset($tree[$u->id]) ? '<ul class="unit-tree">'.$render($u->id).'</ul>' : '').'</li>'; } return $out; }; @endphp
    <ul class="unit-tree root">{!! $render(0) !!}</ul>
</div></div></div>
<div class="col-lg-5">
    @if(can_do('structure.manage'))
    <div class="card mb-3"><div class="card-header">Nouvelle unité</div><div class="card-body"><form method="post" action="{{ route('structure.store') }}" class="row g-2">@csrf
        @include('partials.field', ['name' => 'name', 'label' => 'Nom', 'required' => true, 'col' => 'col-12'])
        @include('partials.field', ['name' => 'code', 'label' => 'Code', 'required' => true, 'col' => 'col-md-5'])
        @include('partials.field', ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => \App\Models\OrgUnit::TYPES, 'required' => true, 'col' => 'col-md-7'])
        @include('partials.field', ['name' => 'parent_id', 'label' => 'Unité parente', 'type' => 'select', 'options' => $units->pluck('name', 'id')->all(), 'col' => 'col-12'])
        <div class="col-12"><button class="btn btn-primary btn-sm">Créer</button></div>
    </form></div></div>
    @endif
    <div class="card"><div class="card-header">Postes vacants ({{ $vacant->count() }})</div>
        <ul class="list-group list-group-flush">@forelse($vacant as $p)<li class="list-group-item d-flex justify-content-between"><span>{{ $p->title }}<br><span class="small text-muted-2">{{ $p->orgUnit?->name }}</span></span><span class="badge text-bg-warning align-self-center">{{ $p->seats - $p->occupied }} / {{ $p->seats }}</span></li>@empty<li class="list-group-item small text-muted-2">Aucun poste vacant.</li>@endforelse</ul>
    </div>
</div>
</div>
@endsection
