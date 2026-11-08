@extends('layouts.app')
@section('title', $replaces ? 'Nouvelle version du barème' : 'Nouveau barème')
@section('content')
@include('partials.head', ['title' => $replaces ? 'Nouvelle version (v'.($replaces->version + 1).') du barème' : 'Nouveau barème', 'crumbs' => ['Frais' => route('fees.index')],
    'sub' => $replaces ? 'Remplace la v'.$replaces->version.' ('.e($replaces->category->label).', '.e($replaces->criteriaLabel()).', '.money($replaces->amount, $replaces->currency).')' : 'Le barème le plus spécifique s’applique (promotion > filière/faculté/cycle/statut > général).'])
@php $rows = old('inst', $replaces ? $replaces->installments->map(fn ($i) => ['label' => $i->label, 'mode' => $i->mode, 'value' => $i->value, 'due_date' => $i->due_date->toDateString()])->all() : [['label' => 'Tranche 1', 'mode' => 'percent', 'value' => '100', 'due_date' => '']]); @endphp
<form method="post" action="{{ route('fees.schedules.store') }}" class="card" style="max-width:1050px" data-cascade>@csrf
    @if($replaces)<input type="hidden" name="replaces_id" value="{{ $replaces->id }}">@endif
    <div class="card-body row g-3">
        @unless($replaces)
            <div class="col-md-4"><label class="form-label" for="s1">Frais *</label><select id="s1" name="fee_category_id" class="form-select" required>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('fee_category_id') == $c->id)>{{ $c->label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="s2">Année *</label><select id="s2" name="academic_year_id" class="form-select" required>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="s3">Faculté</label><select id="s3" name="faculty_id" class="form-select" data-faculty><option value="">Toutes</option>@foreach($faculties as $f)<option value="{{ $f->id }}">{{ $f->code }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="s4">Filière</label><select id="s4" name="program_id" class="form-select"><option value="">Toutes</option>@foreach($programs as $p)<option value="{{ $p->id }}" data-fac="{{ $p->department->faculty_id }}">{{ $p->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="s5">Promotion</label><select id="s5" name="promotion_id" class="form-select"><option value="">Toutes</option>@foreach($promotions as $p)<option value="{{ $p->id }}" data-fac="{{ $p->program->department->faculty_id }}">{{ $p->code }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="s6">Cycle</label><select id="s6" name="cycle_id" class="form-select"><option value="">Tous</option>@foreach($cycles as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="s7">Statut étudiant</label><select id="s7" name="student_status" class="form-select"><option value="">Tous</option>@foreach(\App\Support\Labels::STATUSES['enrollment_status'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        @endunless
        <div class="col-md-2"><label class="form-label" for="s8">Devise *</label><select id="s8" name="currency" class="form-select">@foreach($currencies as $c)<option @selected(old('currency', $replaces?->currency) === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label" for="s9">Montant *</label><input id="s9" name="amount" class="form-control text-end fw-bold" value="{{ old('amount', $replaces?->amount) }}" required inputmode="decimal"></div>
        <div class="col-md-3"><label class="form-label" for="s10">Date d’effet *</label><input id="s10" type="date" name="effective_from" class="form-control" value="{{ old('effective_from', now()->toDateString()) }}" required></div>
        @if($replaces)
            <div class="col-md-8"><label class="form-label" for="s11">Motif de la modification *</label><input id="s11" name="change_reason" class="form-control" required maxlength="255" value="{{ old('change_reason') }}"></div>
            <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="apply_to_existing" value="1" id="ate"><label class="form-check-label" for="ate">Appliquer aux créances existantes de la version précédente (ajustement « révision de barème » tracé ; une baisse est limitée au solde restant)</label></div>
        @endif
    </div>
    <div class="card-header border-top">Tranches <button type="button" class="btn btn-sm btn-light" id="addInst"><i class="bi bi-plus"></i> Ajouter</button></div>
    <div class="table-responsive"><table class="table table-sm mb-0" id="instTable"><thead><tr><th>Libellé</th><th>Mode</th><th class="num">Valeur</th><th>Date limite</th><th></th></tr></thead><tbody>
        @foreach($rows as $i => $r)
            <tr><td><input name="inst[{{ $i }}][label]" class="form-control form-control-sm" value="{{ $r['label'] }}" aria-label="Libellé"></td>
                <td><select name="inst[{{ $i }}][mode]" class="form-select form-select-sm" aria-label="Mode"><option value="percent" @selected($r['mode'] === 'percent')>%</option><option value="amount" @selected($r['mode'] === 'amount')>Montant</option></select></td>
                <td><input name="inst[{{ $i }}][value]" class="form-control form-control-sm text-end" value="{{ $r['value'] }}" required aria-label="Valeur"></td>
                <td><input type="date" name="inst[{{ $i }}][due_date]" class="form-control form-control-sm" value="{{ $r['due_date'] }}" required aria-label="Date limite"></td>
                <td><button type="button" class="btn btn-sm btn-light del" aria-label="Supprimer la tranche"><i class="bi bi-x"></i></button></td></tr>
        @endforeach
    </tbody></table></div>
    <div class="card-body small text-muted-2">Le total des tranches doit être égal au montant (100 % en mode pourcentage ; l’écart d’arrondi éventuel est porté sur la dernière tranche).</div>
    <div class="card-footer bg-white d-flex gap-2"><button class="btn btn-primary">Enregistrer en brouillon</button><a class="btn btn-light" href="{{ route('fees.index') }}">Annuler</a></div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    const tb = document.querySelector('#instTable tbody');
    const renum = () => tb.querySelectorAll('tr').forEach((tr, i) => tr.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/inst\[\d+\]/, `inst[${i}]`)));
    document.getElementById('addInst').onclick = () => { const tr = tb.rows[0].cloneNode(true); tr.querySelectorAll('input').forEach(i => i.value = ''); tb.appendChild(tr); renum(); };
    tb.addEventListener('click', e => { if (e.target.closest('.del') && tb.rows.length > 1) { e.target.closest('tr').remove(); renum(); } });
})();
</script>
@endpush
