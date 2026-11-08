@extends('layouts.app')
@section('title', $customer->exists ? 'Modifier le client' : 'Nouveau client')
@section('content')
<div class="page-head"><h1>{{ $customer->exists ? 'Modifier « '.$customer->name.' »' : 'Nouveau client' }}</h1></div>
<div class="card" style="max-width:760px">
    <form class="card-body row g-3" method="post" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf @if($customer->exists) @method('PUT') @endif
        <div class="col-md-8"><label class="form-label">Nom ou raison sociale *</label><input name="name" class="form-control" value="{{ old('name', $customer->name) }}" required maxlength="190"></div>
        <div class="col-md-4"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}" maxlength="40"></div>
        <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}" maxlength="150"></div>
        <div class="col-md-6"><label class="form-label">Plafond de crédit</label><input name="credit_limit" class="form-control text-end" inputmode="decimal" value="{{ old('credit_limit', $customer->credit_limit) }}" placeholder="Sans plafond"></div>
        <div class="col-12"><label class="form-label">Adresse</label><input name="address" class="form-control" value="{{ old('address', $customer->address) }}" maxlength="255"></div>
        <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" value="{{ old('notes', $customer->notes) }}" maxlength="255"></div>
        <input type="hidden" name="is_active" value="0">
        <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $customer->is_active))><label class="form-check-label" for="act">Actif</label></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-light">Annuler</a></div>
    </form>
</div>
@endsection
