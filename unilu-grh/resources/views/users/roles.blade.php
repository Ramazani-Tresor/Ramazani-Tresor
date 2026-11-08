@extends('layouts.app')
@section('title', 'Rôles et permissions')
@php $canEdit = can_do('roles.manage'); @endphp
@section('content')
@include('partials.head', ['title' => 'Rôles et permissions', 'crumbs' => ['Utilisateurs' => route('users.index')], 'sub' => 'Permissions configurables par rôle. Les permissions sensibles sont signalées. Le contrôle est effectué côté serveur sur chaque opération.'])
@foreach($roles as $role)
<form method="post" action="{{ route('roles.update', $role) }}" class="card mb-3">@csrf @method('PUT')
    <div class="card-header"><span>{{ $role->name }} <span class="small text-muted-2 fw-normal">— {{ $role->description }}</span></span>@if($canEdit && $role->slug !== 'super_admin')<button class="btn btn-sm btn-primary">Enregistrer</button>@endif</div>
    <div class="card-body"><div class="row g-2">
    @foreach($permissions as $module => $perms)<div class="col-md-6 col-xl-4"><div class="form-section">{{ $module }}</div>
        @foreach($perms as $p)<div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p->slug }}" id="p{{ $role->id }}-{{ $p->id }}" @checked($role->permissions->contains('id', $p->id)) @disabled(! $canEdit || $role->slug === 'super_admin')><label class="form-check-label small" for="p{{ $role->id }}-{{ $p->id }}">{{ $p->name }} @if($p->is_sensitive)<i class="bi bi-shield-exclamation text-warning" title="Permission sensible"></i>@endif</label></div>@endforeach
    </div>@endforeach
    </div></div>
</form>
@endforeach
@endsection
