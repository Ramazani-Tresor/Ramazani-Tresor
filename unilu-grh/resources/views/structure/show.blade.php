@extends('layouts.app')
@section('title', $unit->name)
@section('content')
@include('partials.head', ['title' => $unit->name, 'crumbs' => ['Structure' => route('structure.index')], 'sub' => e($unit->typeLabel()).' · code '.e($unit->code).($unit->parent ? ' · rattachée à '.e($unit->parent->name) : '')])
<div class="row g-3">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-header">Postes et responsabilités</div>
    @if($positions->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Poste</th><th>Fonction</th><th>Occupation</th>@if(can_do('structure.manage'))<th></th>@endif</tr></thead><tbody>
    @foreach($positions as $p)<tr><td data-l="Poste" class="fw-600">{{ $p->title }} <span class="mini text-muted-2">{{ $p->code }}</span></td><td data-l="Fonction">{{ $p->jobFunction?->name }}</td><td data-l="Occupation">{!! $p->occupied >= $p->seats ? '<span class="badge-st st-ok">Pourvu</span>' : '<span class="badge-st st-warn">Vacant</span>' !!} {{ $p->occupied }} / {{ $p->seats }}</td>
        @if(can_do('structure.manage'))<td><form method="post" action="{{ route('positions.update', $p) }}" class="d-flex gap-1">@csrf @method('PUT')<input type="hidden" name="title" value="{{ $p->title }}"><input type="hidden" name="job_function_id" value="{{ $p->job_function_id }}"><input type="number" name="seats" value="{{ $p->seats }}" min="1" class="form-control form-control-sm" style="width:80px" aria-label="Nombre de places"><input type="hidden" name="is_active" value="1"><button class="btn btn-sm btn-light">Places</button></form></td>@endif</tr>@endforeach
    </tbody></table></div>@else <div class="card-body small text-muted-2">Aucun poste défini.</div> @endif
    @if(can_do('structure.manage'))<div class="card-body border-top"><form method="post" action="{{ route('positions.store', $unit) }}" class="row g-2 align-items-end">@csrf
        @include('partials.field', ['name' => 'title', 'label' => 'Intitulé du poste', 'required' => true, 'col' => 'col-md-4'])
        @include('partials.field', ['name' => 'code', 'label' => 'Code', 'required' => true, 'col' => 'col-md-2'])
        @include('partials.field', ['name' => 'job_function_id', 'label' => 'Fonction', 'type' => 'select', 'options' => $functions->pluck('name', 'id')->all(), 'col' => 'col-md-3'])
        @include('partials.field', ['name' => 'seats', 'label' => 'Places', 'type' => 'number', 'value' => 1, 'required' => true, 'col' => 'col-md-1'])
        <div class="col-md-2"><button class="btn btn-primary w-100">Ajouter</button></div>
    </form></div>@endif
    </div>
    <div class="card mb-3"><div class="card-header">Agents affectés ({{ $staff->count() }}, sous-unités comprises)</div>
    @if($staff->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Agent</th><th>Grade</th><th>Unité</th><th>Poste</th><th>Depuis</th></tr></thead><tbody>@foreach($staff as $a)<tr><td data-l="Agent">@if(auth()->user()->canOnEmployee('employees.view', $a->employee))<a href="{{ route('employees.show', $a->employee) }}">{{ $a->employee->fullName() }}</a>@else{{ $a->employee->fullName() }}@endif</td><td data-l="Grade">{{ $a->employee->grade?->name }}</td><td data-l="Unité">{{ $a->orgUnit->name }}</td><td data-l="Poste">{{ $a->position?->title }}</td><td data-l="Depuis">{{ $a->start_date->format('d/m/Y') }}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucun agent.</div> @endif
    </div>
    <div class="card"><div class="card-header">Mouvements récents</div>
    @if($moves->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Date</th><th>Agent</th><th>De</th><th>Vers</th><th>Motif</th></tr></thead><tbody>@foreach($moves as $a)<tr><td data-l="Date">{{ $a->start_date->format('d/m/Y') }}</td><td data-l="Agent">{{ $a->employee->fullName() }}</td><td data-l="De">{{ $a->fromUnit?->name ?? '—' }}</td><td data-l="Vers">{{ $a->orgUnit?->name }}</td><td data-l="Motif">{{ $a->reason }}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">—</div> @endif
    </div>
</div>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">Sous-unités</div><ul class="list-group list-group-flush">@forelse($children as $c)<li class="list-group-item"><a href="{{ route('structure.show', $c) }}">{{ $c->name }}</a> <span class="mini text-muted-2">{{ $c->typeLabel() }}</span></li>@empty<li class="list-group-item small text-muted-2">Aucune.</li>@endforelse</ul></div>
    @if(can_do('structure.manage'))
    <div class="card"><div class="card-header">Modifier l’unité</div><div class="card-body"><form method="post" action="{{ route('structure.update', $unit) }}" class="row g-2">@csrf @method('PUT')
        @include('partials.field', ['name' => 'name', 'label' => 'Nom', 'value' => $unit->name, 'required' => true, 'col' => 'col-12'])
        @include('partials.field', ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => \App\Models\OrgUnit::TYPES, 'value' => $unit->type, 'required' => true, 'col' => 'col-12'])
        @include('partials.field', ['name' => 'parent_id', 'label' => 'Unité parente', 'type' => 'select', 'options' => $units->where('id', '!=', $unit->id)->pluck('name', 'id')->all(), 'value' => $unit->parent_id, 'col' => 'col-12'])
        @include('partials.field', ['name' => 'is_active', 'label' => 'Unité active', 'type' => 'bool', 'value' => $unit->is_active, 'col' => 'col-12'])
        <div class="col-12"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
    </form></div></div>
    @endif
</div>
</div>
@endsection
