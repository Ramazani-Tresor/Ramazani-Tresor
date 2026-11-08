@extends('layouts.app')
@section('title', 'Ligne de relevé #'.$line->id)
@section('content')
@include('partials.head', ['title' => 'Ligne de relevé #'.$line->id, 'crumbs' => ['Banque' => route('bank.index'), 'Lignes' => route('bank.lines')],
    'sub' => \App\Support\Labels::badge('line_match', $line->match_status).' '.\App\Support\Labels::badge('line_review', $line->review_status).' · lot <a href="'.route('bank.batches.show', $line->batch).'">'.e($line->batch->number).'</a> (ligne '.$line->source_row.' du fichier)'])
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-body"><dl class="dl-grid">
            <div><dt>Compte</dt><dd>{{ $line->bankAccount->display() }}</dd></div><div><dt>Date d’opération</dt><dd>{{ $line->operation_date->format('d/m/Y') }}</dd></div>
            <div><dt>Date de valeur</dt><dd>{{ $line->value_date?->format('d/m/Y') ?? '—' }}</dd></div><div><dt>Référence</dt><dd>{{ $line->reference ?? '—' }}</dd></div>
            <div><dt>Libellé</dt><dd>{{ $line->description ?? '—' }}</dd></div><div><dt>Montant</dt><dd class="fs-5 fw-bold">{{ money($line->amount, $line->currency) }}</dd></div>
            <div><dt>Reste à rapprocher</dt><dd>{{ money($line->remaining(), $line->currency) }}</dd></div>
            @if($line->reliability_note)<div><dt>Fiabilité</dt><dd class="text-warning">{{ $line->reliability_note }}</dd></div>@endif
            @if($line->ignore_reason)<div><dt>Écartée</dt><dd>{{ $line->ignore_reason }}</dd></div>@endif
        </dl></div></div>
        @if($line->review_status === 'pending' && can_do('bank.review'))
            <form method="post" action="{{ route('bank.review', $line) }}" class="card mb-3">@csrf
                <div class="card-header">Vérification humaine de l’extraction</div>
                <div class="card-body row g-2">
                    <p class="small text-muted-2 mb-1">Comparez avec le document source et corrigez si nécessaire.</p>
                    <div class="col-6"><label class="form-label" for="ro">Date d’opération</label><input id="ro" type="date" name="operation_date" class="form-control form-control-sm" value="{{ $line->operation_date->toDateString() }}"></div>
                    <div class="col-6"><label class="form-label" for="rv">Date de valeur</label><input id="rv" type="date" name="value_date" class="form-control form-control-sm" value="{{ $line->value_date?->toDateString() }}"></div>
                    <div class="col-6"><label class="form-label" for="rr">Référence</label><input id="rr" name="reference" class="form-control form-control-sm" value="{{ $line->reference }}"></div>
                    <div class="col-6"><label class="form-label" for="ra">Montant</label><input id="ra" name="amount" class="form-control form-control-sm text-end" value="{{ $line->amount }}"></div>
                    <div class="col-12"><input name="note" class="form-control form-control-sm" placeholder="Observation" aria-label="Observation"></div>
                </div>
                <div class="card-footer bg-white d-flex gap-2"><button class="btn btn-accent btn-sm" name="decision" value="accept">Valider l’extraction</button><button class="btn btn-outline-danger btn-sm" name="decision" value="reject" data-confirm="Rejeter cette ligne extraite ?">Rejeter</button></div>
            </form>
        @endif
        <div class="card"><div class="card-header">Rapprochements</div><ul class="list-group list-group-flush">
            @forelse($line->matches as $m)<li class="list-group-item small"><a href="{{ route('payments.show', $m->payment) }}">{{ $m->payment->number }}</a> — {{ $m->payment->student->matricule }} — {{ money($m->amount, $line->currency) }} — {{ $m->method === 'auto_exact' ? 'automatique' : 'manuel' }} par {{ $m->creator->name }} — {{ $m->status === 'active' ? 'actif' : 'annulé' }}{{ $m->note ? ' — '.$m->note : '' }}
                @if($m->status === 'active' && can_do('bank.reconcile'))<form method="post" action="{{ route('bank.unmatch', $m) }}" class="d-flex gap-1 mt-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif d’annulation"><button class="btn btn-sm btn-outline-danger">Annuler</button></form>@endif</li>
            @empty<li class="list-group-item small text-muted-2">Aucun.</li>@endforelse
        </ul></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-header">Correspondances proposées <span class="small fw-normal text-muted-2">critères : référence (50), montant (30), date ±{{ $tolerance }} j (20) — même compte et même devise</span></div>
            @if(in_array($line->review_status, ['pending', 'rejected']))<div class="card-body text-muted-2 small">La ligne doit être vérifiée avant tout rapprochement.</div>@endif
            <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Paiement</th><th>Étudiant</th><th>Date</th><th>Référence</th><th class="num">Reste non confirmé</th><th>Score</th><th></th></tr></thead><tbody>
            @php $exactCount = $candidates->where('exact', true)->count(); @endphp
            @forelse($candidates as $c)
                @php $p = $c['payment']; $rest = \App\Support\Money::money(\App\Support\Money::sub($p->amount, $p->bank_confirmed_amount)); @endphp
                <tr class="{{ $c['exact'] ? 'table-success' : '' }}"><td data-l="Paiement"><a href="{{ route('payments.show', $p) }}">{{ $p->number }}</a><div class="small">{!! \App\Support\Labels::badge('payment', $p->status) !!}</div></td><td data-l="Étudiant" class="small">{{ $p->student->matricule }}<br>{{ $p->student->fullName() }}</td>
                    <td data-l="Date">{{ $p->deposit_date->format('d/m/Y') }} <span class="small text-muted-2">({{ $c['criteria']['days'] }} j)</span></td><td data-l="Réf.">{{ $p->bank_reference }}</td><td data-l="Reste" class="num">{{ money($rest, $p->currency) }}</td>
                    <td data-l="Score"><span class="badge-st st-{{ $c['exact'] ? 'ok' : 'info' }}">{{ $c['score'] }}</span> <span class="small">{{ $c['criteria']['reference'] ? 'réf.' : '' }} {{ $c['criteria']['amount'] ? 'montant' : '' }} {{ $c['criteria']['date'] ? 'date' : '' }}</span></td>
                    <td>@if(can_do('bank.reconcile') && in_array($line->review_status, ['not_required', 'verified']))
                        <form method="post" action="{{ route('bank.match', $line) }}" class="d-grid gap-1" style="min-width:170px">@csrf<input type="hidden" name="payment_id" value="{{ $p->id }}">
                            <input name="amount" class="form-control form-control-sm text-end" value="{{ \App\Support\Money::money(\App\Support\Money::min($rest, $line->remaining())) }}" aria-label="Montant à rapprocher">
                            <input name="note" class="form-control form-control-sm" placeholder="{{ $exactCount > 1 ? 'Justifier le choix (ambigu)' : 'Commentaire' }}" @required($exactCount > 1) aria-label="Commentaire">
                            <button class="btn btn-sm btn-primary">Rapprocher</button></form>@endif</td></tr>
            @empty<tr><td colspan="7">@include('partials.empty', ['text' => 'Aucune correspondance plausible : paiement non saisi, autre compte, ou ligne à écarter.'])</td></tr>@endforelse
            </tbody></table></div>
            @if($exactCount > 1)<div class="card-body alert alert-warning m-2 small">Cas ambigu : {{ $exactCount }} paiements correspondent exactement. Aucun rapprochement automatique n’a été appliqué ; vérifiez les bordereaux originaux avant de choisir.</div>@endif
        </div>
        @if(in_array($line->match_status, ['unmatched']) && can_do('bank.reconcile'))
            <form method="post" action="{{ route('bank.ignore', $line) }}" class="card mt-3 card-body d-flex flex-row gap-2 align-items-end">@csrf<div class="flex-grow-1"><label class="form-label" for="ig">Écarter cette ligne (ex. opération non liée aux frais académiques)</label><input id="ig" name="reason" class="form-control" required maxlength="255"></div><button class="btn btn-outline-secondary">Écarter</button></form>
        @endif
    </div>
</div>
@endsection
