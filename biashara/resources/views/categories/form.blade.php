@extends('layouts.app')
@section('title', $category->exists ? 'Modifier la catégorie' : 'Nouvelle catégorie')
@section('content')
<div class="page-head"><h1>{{ $category->exists ? 'Modifier « '.$category->name.' »' : 'Nouvelle catégorie' }}</h1></div>
<div class="card" style="max-width:640px">
    <form class="card-body" method="post" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}">
        @csrf @if($category->exists) @method('PUT') @endif
        <div class="mb-3"><label class="form-label">Nom *</label><input name="name" class="form-control" value="{{ old('name', $category->name) }}" required maxlength="120" autofocus></div>
        <div class="mb-3"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ old('description', $category->description) }}" maxlength="255"></div>
        <div class="mb-3"><label class="form-label">Couleur (point de vente)</label><input type="color" name="color" class="form-control form-control-color" value="{{ old('color', $category->color ?? '#1E6FD9') }}"></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $category->is_active))><label class="form-check-label" for="act">Active</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a href="{{ route('categories.index') }}" class="btn btn-light">Annuler</a></div>
    </form>
</div>
@endsection
