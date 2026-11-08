@extends('layouts.app')
@section('title', $supplier->exists ? 'Modifier le fournisseur' : 'Nouveau fournisseur')
@section('content')
<div class="page-head"><h1>{{ $supplier->exists ? 'Modifier « '.$supplier->name.' »' : 'Nouveau fournisseur' }}</h1></div>
<div class="card" style="max-width:760px">
    <form class="card-body row g-3" method="post" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
        @csrf @if($supplier->exists) @method('PUT') @endif
        <div class="col-md-6"><label class="form-label">Nom / raison sociale *</label><input name="name" class="form-control" value="{{ old('name', $supplier->name) }}" required maxlength="190"></div>
        <div class="col-md-6"><label class="form-label">Personne de contact</label><input name="contact_person" class="form-control" value="{{ old('contact_person', $supplier->contact_person) }}" maxlength="120"></div>
        <div class="col-md-6"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}" maxlength="40"></div>
        <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email) }}" maxlength="150"></div>
        <div class="col-12"><label class="form-label">Adresse</label><input name="address" class="form-control" value="{{ old('address', $supplier->address) }}" maxlength="255"></div>
        <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" value="{{ old('notes', $supplier->notes) }}" maxlength="255"></div>
        <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $supplier->is_active))><label class="form-check-label" for="act">Actif</label></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a href="{{ route('suppliers.index') }}" class="btn btn-light">Annuler</a></div>
    </form>
</div>
@endsection
