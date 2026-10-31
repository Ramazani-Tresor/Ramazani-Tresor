@extends('layouts.app')
@section('title', 'Contrat de paiement')
@section('content')
@include('partials.head', ['title' => $contract ? 'Nouvelle version du contrat '.$contract->number : 'Nouveau contrat de paiement', 'crumbs' => ['Étudiant' => route('students.show', $student)], 'sub' => e($student->matricule).' — '.e($student->fullName())])
@php $rows = old('inst', $contract ? $contract->installments->map(fn ($i) => ['amount' => $i->amount, 'due_date' => $i->due_date->toDateString()])->all() : [['amount' => '', 'due_date' => '']]); @endphp
<form method="post" action="{{ $contract ? route('contracts.version.store', $contract) : route('contracts.store', $student) }}" class="card" style="max-width:960px">@csrf
    <input type="hidden" name="academic_year_id" value="{{ $yearId }}">
    <div class="card-header">Créances couvertes (une seule devise par contrat)</div>
    <div class="card-body">
        @forelse($charges as $c)
            <div class="form-check"><input class="form-check-input" type="checkbox" name="charge_ids[]" value="{{ $c->id }}" id="ch{{ $c->id }}" checked @disabled($contract)><label class="form-check-label" for="ch{{ $c->id }}">{{ $c->category->label }} — solde {{ money($c->balance(), $c->currency) }}</label></div>
        @empty
            @include('partials.empty', ['text' => 'Aucune créance ouverte : pas de contrat nécessaire.'])
        @endforelse
        @if($contract)<p class="small text-muted-2 mt-2">Les créances couvertes restent celles du contrat initial. Le solde est recalculé à la date de la nouvelle version.</p>@endif
    </div>
    <div class="card-header border-top">Échéancier convenu (total = solde couvert) <button type="button" class="btn btn-sm btn-light" id="addRow"><i class="bi bi-plus"></i> Échéance</button></div>
    <div class="table-responsive"><table class="table table-sm mb-0" id="instT"><thead><tr><th class="num">Montant</th><th>Date</th><th></th></tr></thead><tbody>
        @foreach($rows as $i => $r)<tr><td><input name="inst[{{ $i }}][amount]" class="form-control form-control-sm text-end" value="{{ $r['amount'] }}" required aria-label="Montant"></td><td><input type="date" name="inst[{{ $i }}][due_date]" class="form-control form-control-sm" value="{{ $r['due_date'] }}" required aria-label="Date"></td><td><button type="button" class="btn btn-sm btn-light del" aria-label="Supprimer"><i class="bi bi-x"></i></button></td></tr>@endforeach
    </tbody></table></div>
    <div class="card-body">
        <label class="form-label" for="cond">Conditions</label><textarea id="cond" name="conditions" class="form-control" rows="4" maxlength="3000">{{ old('conditions', $contract?->conditions ?? setting('contract_default_conditions')) }}</textarea>
        @if($contract)<label class="form-label mt-2" for="rs">Motif de la nouvelle version *</label><input id="rs" name="reason" class="form-control" required maxlength="255">@endif
    </div>
    <div class="card-footer bg-white d-flex gap-2"><button class="btn btn-primary" @disabled($charges->isEmpty())>Enregistrer</button><a href="{{ route('students.show', $student) }}" class="btn btn-light">Annuler</a></div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    const tb = document.querySelector('#instT tbody');
    const renum = () => tb.querySelectorAll('tr').forEach((tr, i) => tr.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/inst\[\d+\]/, `inst[${i}]`)));
    document.getElementById('addRow').onclick = () => { const tr = tb.rows[0].cloneNode(true); tr.querySelectorAll('input').forEach(i => i.value = ''); tb.appendChild(tr); renum(); };
    tb.addEventListener('click', e => { if (e.target.closest('.del') && tb.rows.length > 1) { e.target.closest('tr').remove(); renum(); } });
})();
</script>
@endpush
