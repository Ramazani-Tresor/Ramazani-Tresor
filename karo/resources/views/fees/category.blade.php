@extends('layouts.app')
@section('title', $category->exists ? 'Modifier le frais' : 'Nouveau frais')
@section('content')
@include('partials.head', ['title' => $category->exists ? 'Modifier « '.$category->label.' »' : 'Nouveau frais', 'crumbs' => ['Frais' => route('fees.index')]])
<form method="post" action="{{ $category->exists ? route('fees.categories.update', $category) : route('fees.categories.store') }}" class="card" style="max-width:960px">@csrf @if($category->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label" for="c1">Code *</label><input id="c1" name="code" class="form-control text-uppercase" value="{{ old('code', $category->code) }}" required maxlength="30"></div>
        <div class="col-md-6"><label class="form-label" for="c2">Libellé *</label><input id="c2" name="label" class="form-control" value="{{ old('label', $category->label) }}" required maxlength="150"></div>
        <div class="col-md-3"><label class="form-label" for="c3">Priorité d’imputation</label><input id="c3" type="number" name="priority" class="form-control" value="{{ old('priority', $category->priority) }}" min="1" max="999"></div>
        <div class="col-md-4"><label class="form-label" for="c4">Nature</label><select id="c4" name="nature" class="form-select"><option value="mandatory" @selected($category->nature === 'mandatory')>Obligatoire</option><option value="conditional" @selected($category->nature === 'conditional')>Conditionnel</option></select></div>
        <div class="col-md-4"><label class="form-label" for="c5">Établissement de la créance</label><select id="c5" name="trigger" class="form-select"><option value="enrollment" @selected($category->trigger === 'enrollment')>À l’inscription</option><option value="exam_session" @selected($category->trigger === 'exam_session')>À l’inscription à une session d’examens</option><option value="manual" @selected($category->trigger === 'manual')>Sur décision (créance manuelle)</option></select></div>
        <div class="col-md-2"><label class="form-label" for="c6">Semestre</label><select id="c6" name="session_semester" class="form-select"><option value="">—</option><option value="1" @selected($category->session_semester == 1)>1</option><option value="2" @selected($category->session_semester == 2)>2</option></select></div>
        <div class="col-md-2"><label class="form-label" for="c7">Session</label><select id="c7" name="session_type" class="form-select"><option value="">—</option><option value="ordinary" @selected($category->session_type === 'ordinary')>Ordinaire</option><option value="resit" @selected($category->session_type === 'resit')>Rattrapage</option></select></div>
        <fieldset class="col-md-6"><legend class="form-label">Statuts concernés (aucun coché = tous)</legend>@foreach(\App\Support\Labels::STATUSES['enrollment_status'] as $k => $v)<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="applicable_statuses[]" value="{{ $k }}" id="st{{ $k }}" @checked(in_array($k, $category->applicable_statuses ?? []))><label class="form-check-label" for="st{{ $k }}">{{ $v }}</label></div>@endforeach
            <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="final_promotions_only" value="1" id="fpo" @checked($category->final_promotions_only)><label class="form-check-label" for="fpo">Uniquement les promotions finales (ex. Bac3, Master2)</label></div></fieldset>
        <div class="col-md-3"><label class="form-label" for="c8">Valide du</label><input id="c8" type="date" name="valid_from" class="form-control" value="{{ $category->valid_from?->toDateString() }}"></div>
        <div class="col-md-3"><label class="form-label" for="c9">au</label><input id="c9" type="date" name="valid_until" class="form-control" value="{{ $category->valid_until?->toDateString() }}"></div>
        <fieldset class="col-12"><legend class="form-label">Comptes bancaires autorisés pour ce frais</legend>
            @foreach($accounts as $a)<div class="form-check"><input class="form-check-input" type="checkbox" name="accounts[]" value="{{ $a->id }}" id="a{{ $a->id }}" @checked($category->bankAccounts?->contains('id', $a->id))><label class="form-check-label" for="a{{ $a->id }}">{{ $a->display() }}</label></div>@endforeach
            @if($accounts->isEmpty())<p class="small text-danger">Aucun compte : créez-les dans Paramètres › Banques.</p>@endif</fieldset>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked($category->is_active)><label class="form-check-label" for="act">Actif</label></div>
    </div>
    <div class="card-footer bg-white d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a class="btn btn-light" href="{{ route('fees.index') }}">Annuler</a></div>
</form>
@endsection
