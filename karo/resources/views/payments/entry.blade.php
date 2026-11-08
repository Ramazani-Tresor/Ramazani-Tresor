@extends('layouts.app')
@section('title', 'Saisie des bordereaux')
@section('content')
@include('partials.head', ['title' => 'Saisie d’un bordereau bancaire', 'sub' => 'Le bordereau est soumis au contrôle : il n’a d’effet sur le solde qu’après contrôle du justificatif puis validation par d’autres personnes.'])
<div class="steps" aria-label="Étapes">
    @php $st = $student ? 6 : ($students->isNotEmpty() ? 4 : 1); @endphp
    @foreach(['Année', 'Faculté', 'Promotion', 'Étudiants', 'Sélection', 'Situation', 'Bordereau', 'Répartition', 'Justificatif et soumission'] as $i => $s)<span class="{{ $i + 1 < $st ? 'done' : ($i + 1 === $st ? 'on' : '') }}">{{ $s }}</span>@endforeach
</div>
@if($year && $year->status !== 'open')<div class="alert alert-warning">L’année {{ $year->code }} n’est pas ouverte : aucune saisie n’est possible.</div>@endif

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-header">1–3. Année, faculté, promotion</div><div class="card-body">
            <form method="get" data-cascade class="d-grid gap-2">
                @php $o = $filters->options(); @endphp
                <div><label class="form-label" for="e-y">Année académique</label><select id="e-y" name="year" class="form-select">@foreach($o['years']->where('status', 'open') as $y)<option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->code }}</option>@endforeach</select></div>
                <div><label class="form-label" for="e-f">Faculté</label><select id="e-f" name="faculty" class="form-select" data-faculty><option value="">— Choisir —</option>@foreach($o['faculties'] as $f)<option value="{{ $f->id }}" @selected($filters->get('faculty') == $f->id)>{{ $f->code }} — {{ $f->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="e-p">Promotion</label><select id="e-p" name="promotion" class="form-select"><option value="">— Toutes —</option>@foreach($o['promotions'] as $p)<option value="{{ $p->id }}" data-fac="{{ $p->program->department->faculty_id }}" @selected($filters->get('promotion') == $p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="e-q">Rechercher (matricule ou nom)</label><input id="e-q" type="search" name="q" value="{{ $filters->get('q') }}" class="form-control"></div>
                <button class="btn btn-primary"><i class="bi bi-people"></i> Afficher les étudiants</button>
            </form>
        </div></div>
        <div class="card"><div class="card-header">4–5. Étudiants ({{ $students->count() }}{{ $students->count() >= 60 ? '+, affinez' : '' }})</div>
            <div class="list-group list-group-flush" style="max-height:420px;overflow:auto">
                @forelse($students as $s)
                    <a class="list-group-item list-group-item-action {{ $student?->id === $s->id ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['student' => $s->id]) }}" @if($student?->id === $s->id) aria-current="true" @endif>
                        <div class="fw-600">{{ $s->matricule }}</div><div class="small">{{ $s->fullName() }} · {{ $s->enrollments->first()?->promotion->code }}</div></a>
                @empty
                    <div class="list-group-item">@include('partials.empty', ['icon' => 'bi-funnel', 'text' => 'Choisissez une faculté et une promotion, ou recherchez un étudiant.'])</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        @if(!$student)
            <div class="card">@include('partials.empty', ['icon' => 'bi-person-check', 'text' => 'Sélectionnez un étudiant pour afficher sa situation et saisir le bordereau.'])</div>
        @else
            <div class="card mb-3"><div class="card-header">6. Situation de {{ $student->fullName() }} ({{ $student->matricule }}) — {{ $year->code }} <a href="{{ route('students.show', [$student, 'year' => $year->id]) }}" class="small fw-normal">Dossier complet</a></div>
                <div class="card-body">@include('students._situation', ['compact' => true])</div></div>

            <form method="post" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="card" id="slipForm" data-confirm="Soumettre ce bordereau au contrôle ?">@csrf
                <input type="hidden" name="client_uuid" data-uuid value="{{ old('client_uuid') }}">
                <input type="hidden" name="academic_year_id" value="{{ $year->id }}"><input type="hidden" name="student_id" value="{{ $student->id }}">
                <div class="card-header">7. Informations du bordereau</div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label" for="acc">Banque et compte bénéficiaire *</label>
                        <select id="acc" name="bank_account_id" class="form-select" required>@foreach($accounts as $a)<option value="{{ $a->id }}" data-cur="{{ $a->currency }}" data-fees="{{ $a->feeCategories->pluck('id')->join(',') }}" @selected(old('bank_account_id') == $a->id)>{{ $a->bank->name }} — {{ $a->account_number }} ({{ $a->currency }})</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="ref">Référence bancaire du bordereau *</label><input id="ref" name="bank_reference" class="form-control" value="{{ old('bank_reference') }}" required maxlength="80" autocomplete="off"></div>
                    <div class="col-md-4"><label class="form-label" for="dd">Date du versement (banque) *</label><input id="dd" type="date" name="deposit_date" class="form-control" value="{{ old('deposit_date') }}" max="{{ now()->toDateString() }}" required></div>
                    <div class="col-md-4"><label class="form-label" for="am">Montant versé *</label><div class="input-group"><input id="am" name="amount" class="form-control text-end fw-bold" inputmode="decimal" value="{{ old('amount') }}" required><span class="input-group-text" id="curLbl">USD</span></div></div>
                    <div class="col-md-4"><label class="form-label" for="dep">Déposant (si différent)</label><input id="dep" name="depositor_name" class="form-control" value="{{ old('depositor_name') }}" maxlength="150"></div>
                </div>
                <div class="card-header border-top">8. Répartition entre les frais <button type="button" class="btn btn-sm btn-light" id="autoAlloc"><i class="bi bi-magic"></i> Répartir automatiquement (ordre de priorité)</button></div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Frais</th><th class="num">Solde dû</th><th style="width:170px" class="num">Montant affecté</th></tr></thead>
                    <tbody>
                    @forelse($open as $l)
                        @php $c = $l['charge']; @endphp
                        <tr class="alloc-row" data-cur="{{ $c->currency }}" data-cat="{{ $c->fee_category_id }}" data-balance="{{ $l['balance'] }}">
                            <td><label for="al{{ $c->id }}">{{ $c->category->label }}{{ $c->origin === 'carried_over' ? ' (arriéré)' : '' }}</label> <span class="badge-st st-muted">{{ $c->currency }}</span><div class="small text-danger d-none acc-warn">Compte non autorisé pour ce frais</div></td>
                            <td class="num">{{ money($l['balance'], $c->currency) }}</td>
                            <td><input id="al{{ $c->id }}" name="allocations[{{ $c->id }}]" class="form-control form-control-sm text-end alloc" inputmode="decimal" value="{{ old('allocations.'.$c->id) }}"></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">@include('partials.empty', ['text' => 'Aucune créance ouverte : le versement sera conservé comme montant non affecté (crédit).'])</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot><tr><th colspan="2" class="text-end">Affecté / non affecté</th><th class="num" id="allocSum">—</th></tr></tfoot>
                </table></div>
                <div class="card-body border-top">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-7"><label class="form-label" for="proof">9. Justificatif (bordereau numérisé : PDF, JPG, PNG — 5 Mo) {{ setting('require_proof_for_submission') === '1' ? '*' : '' }}</label><input id="proof" type="file" name="proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png" @required(setting('require_proof_for_submission') === '1')></div>
                        <div class="col-md-5 text-md-end"><button class="btn btn-accent btn-lg" id="submitSlip" @disabled($year->status !== 'open')><i class="bi bi-send-check"></i> Soumettre au contrôle</button></div>
                    </div>
                    <div id="allocError" class="alert alert-danger mt-3 d-none" role="alert"></div>
                    <p class="small text-muted-2 mt-2 mb-0">Un versement réparti entre plusieurs frais reste un paiement unique. Un justificatif présenté par l’étudiant ne vaut pas confirmation bancaire : celle-ci résulte du rapprochement avec le relevé.</p>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const f = document.getElementById('slipForm'); if (!f) return;
    // Calcul en centimes entiers (pas de flottants) pour l'affichage ; le serveur recalcule et contrôle tout.
    const toC = (v) => { const s = String(v || '').replace(/\s/g, '').replace(',', '.'); if (!/^\d*(\.\d{0,2})?$/.test(s) || s === '') return 0; const [i, d = ''] = s.split('.'); return parseInt(i || '0', 10) * 100 + parseInt((d + '00').slice(0, 2), 10); };
    const fmt = (c) => (c / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const acc = document.getElementById('acc'), am = document.getElementById('am'), err = document.getElementById('allocError');
    function refresh() {
        const o = acc.selectedOptions[0], cur = o.dataset.cur, allowed = (o.dataset.fees || '').split(',');
        document.getElementById('curLbl').textContent = cur;
        let sum = 0, msg = '';
        f.querySelectorAll('.alloc-row').forEach(r => {
            const inp = r.querySelector('.alloc'), v = toC(inp.value), sameCur = r.dataset.cur === cur, okAcc = allowed.includes(r.dataset.cat);
            inp.disabled = !sameCur; if (!sameCur) inp.value = '';
            r.querySelector('.acc-warn').classList.toggle('d-none', okAcc || !sameCur);
            if (v > toC(r.dataset.balance)) { msg = 'Une affectation dépasse le solde dû du frais.'; inp.classList.add('is-invalid'); } else inp.classList.remove('is-invalid');
            if (sameCur) sum += v;
        });
        const total = toC(am.value);
        if (sum > total) msg = 'Le total affecté dépasse le montant versé.';
        document.getElementById('allocSum').textContent = fmt(sum) + ' / ' + fmt(Math.max(0, total - sum)) + ' ' + cur;
        err.textContent = msg; err.classList.toggle('d-none', !msg);
        document.getElementById('submitSlip').disabled = !!msg || {{ $year?->status === 'open' ? 'false' : 'true' }};
    }
    document.getElementById('autoAlloc').addEventListener('click', () => {
        let rest = toC(am.value); const cur = acc.selectedOptions[0].dataset.cur;
        f.querySelectorAll('.alloc-row').forEach(r => { const inp = r.querySelector('.alloc'); if (r.dataset.cur !== cur) return; const b = toC(r.dataset.balance), take = Math.min(b, rest); inp.value = take ? (take / 100).toFixed(2) : ''; rest -= take; });
        refresh();
    });
    f.addEventListener('input', refresh); acc.addEventListener('change', refresh); refresh();
})();
</script>
@endpush
