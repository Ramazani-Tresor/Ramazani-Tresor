@extends('layouts.app')
@section('title', 'Rôles et permissions')
@section('content')
@include('partials.head', ['title' => 'Rôles × permissions', 'crumbs' => ['Utilisateurs' => route('users.index')], 'sub' => 'Permissions vérifiées côté serveur sur chaque opération. <span class="badge-st st-warn">Approbation</span> = permission financière sensible, jamais cumulable avec l’administration technique.'])
<ul class="nav nav-tabs" role="tablist">@foreach($roles as $r)<li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#r{{ $r->id }}" type="button" role="tab">{{ $r->name }} <span class="badge text-bg-light">{{ $r->assignments_count }}</span></button></li>@endforeach</ul>
<div class="tab-content card border-top-0 rounded-top-0">
    @foreach($roles as $r)
        <form class="tab-pane fade {{ $loop->first ? 'show active' : '' }} card-body" id="r{{ $r->id }}" method="post" action="{{ route('roles.update', $r) }}" role="tabpanel">@csrf @method('PUT')
            <p class="text-muted-2">{{ $r->description }}</p>
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="requires_2fa" value="1" id="tf{{ $r->id }}" @checked($r->requires_2fa)><label class="form-check-label" for="tf{{ $r->id }}">Double authentification obligatoire</label></div>
            <div class="row g-3">@foreach($permissions as $module => $perms)<div class="col-md-6 col-xl-4"><fieldset class="border rounded-3 p-3 h-100"><legend class="fs-6 fw-bold text-navy float-none w-auto px-1">{{ $module }}</legend>
                @foreach($perms as $p)<div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p->id }}" id="p{{ $r->id }}-{{ $p->id }}" @checked($r->permissions->contains('id', $p->id))><label class="form-check-label small" for="p{{ $r->id }}-{{ $p->id }}">{{ $p->name }} @if($p->is_sensitive)<span class="badge-st st-warn">Approbation</span>@endif</label></div>@endforeach
            </fieldset></div>@endforeach</div>
            <button class="btn btn-primary mt-3" data-confirm="Enregistrer les permissions du rôle « {{ $r->name }} » ? Effet immédiat pour {{ $r->assignments_count }} habilitation(s).">Enregistrer</button>
        </form>
    @endforeach
</div>
@endsection
