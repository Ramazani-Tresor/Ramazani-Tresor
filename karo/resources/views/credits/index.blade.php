@extends('layouts.app')
@section('title', 'Excédents')
@section('content')
@include('partials.head', ['title' => 'Excédents, compensations, remboursements et reports', 'sub' => 'Un excédent est la part d’un paiement validé non affectée. Toute utilisation est demandée puis approuvée par une autre personne ; une demande en attente réserve le montant (pas de double utilisation).'])
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3"><div class="card-header">Crédits disponibles ({{ $credits->count() }})</div>
            <div class="table-responsive"><table class="table table-sm table-hover table-stack mb-0"><thead><tr><th>Paiement</th><th>Étudiant</th><th>Année</th><th class="num">Disponible</th><th></th></tr></thead><tbody>
            @forelse($credits as $p)<tr class="{{ $selected?->id === $p->id ? 'table-active' : '' }}"><td data-l="Paiement"><a href="{{ route('payments.show', $p) }}">{{ $p->number }}</a>{{ $p->type === 'carry_forward' ? ' (report)' : '' }}</td><td data-l="Étudiant">{{ $p->student->matricule }}<div class="small">{{ $p->student->fullName() }}</div></td><td data-l="Année">{{ $p->academicYear->code }}</td><td data-l="Disponible" class="num fw-600">{{ money($p->availableAmount(), $p->currency) }}</td><td>@if(can_do('credits.request'))<a class="btn btn-sm btn-light" href="{{ route('credits.index', ['payment' => $p->id]) }}">Utiliser</a>@endif</td></tr>
            @empty<tr><td colspan="5">@include('partials.empty', ['text' => 'Aucun crédit disponible.'])</td></tr>@endforelse
            </tbody></table></div></div>
        <div class="card"><div class="card-header">Historique</div><div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>N°</th><th>Type</th><th>Étudiant</th><th class="num">Montant</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
            @forelse($history as $o)<tr><td data-l="N°" class="small">{{ $o->number }}</td><td data-l="Type">{{ status_label('credit_type', $o->type) }}</td><td data-l="Étudiant">{{ $o->student->matricule }}</td><td data-l="Montant" class="num">{{ money($o->amount, $o->currency) }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('credit', $o->status) !!}</td><td data-l="Décision" class="small">{{ $o->approver?->name }} {{ $o->decided_at?->format('d/m/Y') }}{{ $o->decision_note ? ' — '.$o->decision_note : '' }}</td></tr>
            @empty<tr><td colspan="6">@include('partials.empty')</td></tr>@endforelse</tbody></table></div></div>
    </div>
    <div class="col-xl-5">
        @if($selected && can_do('credits.request'))
            <form method="post" action="{{ route('credits.store', $selected) }}" class="card mb-3" data-confirm="Enregistrer cette demande ?">@csrf
                <input type="hidden" name="client_uuid" data-uuid>
                <div class="card-header">Utiliser le crédit de {{ $selected->number }} — {{ money($selected->availableAmount(), $selected->currency) }}</div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label" for="ct">Opération</label><select id="ct" name="type" class="form-select" onchange="document.querySelectorAll('[data-t]').forEach(e => e.hidden = e.dataset.t !== this.value)">
                        <option value="compensation">Compensation avec un autre frais</option><option value="refund">Remboursement</option>@if($nextYear)<option value="carry_forward">Report sur {{ $nextYear->code }}</option>@endif</select></div>
                    <div class="mb-2" data-t="compensation"><label class="form-label" for="cc">Créance à compenser (même devise ou taux approuvé)</label><select id="cc" name="target_charge_id" class="form-select">@foreach($targets as $c)<option value="{{ $c->id }}">{{ $c->academicYear->code }} — {{ $c->category->label }} — solde {{ money($c->balance(), $c->currency) }}</option>@endforeach</select></div>
                    @if($nextYear)<input type="hidden" name="target_academic_year_id" value="{{ $nextYear->id }}">@endif
                    <div class="mb-2" data-t="refund" hidden><label class="form-label" for="crm">Mode de remboursement</label><input id="crm" name="refund_method" class="form-control" placeholder="Virement, chèque…" maxlength="80"></div>
                    <div class="mb-2"><label class="form-label" for="ca">Montant</label><input id="ca" name="amount" class="form-control text-end" value="{{ $selected->availableAmount() }}" required></div>
                    <div class="mb-2"><label class="form-label" for="cr">Motif *</label><input id="cr" name="reason" class="form-control" required maxlength="255"></div>
                    <p class="small text-muted-2 mb-0">« Conserver en crédit » : ne rien faire, le montant reste disponible.</p>
                </div>
                <div class="card-footer bg-white"><button class="btn btn-primary">Soumettre à approbation</button></div>
            </form>
        @endif
        <div class="card"><div class="card-header">Demandes en attente ({{ $pending->count() }})</div><ul class="list-group list-group-flush">
            @forelse($pending as $o)
                <li class="list-group-item"><div class="d-flex justify-content-between"><strong>{{ $o->number }} — {{ status_label('credit_type', $o->type) }}</strong><span class="money">{{ money($o->amount, $o->currency) }}</span></div>
                    <div class="small">{{ $o->student->matricule }} · source {{ $o->sourcePayment->number }}{{ $o->targetCharge ? ' → '.$o->targetCharge->category->label : '' }}{{ $o->targetYear ? ' → '.$o->targetYear->code : '' }} · {{ $o->reason }} · demandé par {{ $o->requester->name }}</div>
                    @if(can_do('credits.approve') && $o->requested_by !== auth()->id())
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <form method="post" action="{{ route('credits.approve', $o) }}" class="d-flex gap-1" data-confirm="Approuver et exécuter cette opération ?">@csrf @if($o->type === 'refund')<input name="refund_reference" class="form-control form-control-sm" placeholder="Réf. du remboursement" aria-label="Référence du remboursement">@endif<button class="btn btn-sm btn-accent">Approuver</button></form>
                            <form method="post" action="{{ route('credits.reject', $o) }}" class="d-flex gap-1">@csrf<input name="note" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif du rejet"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>
                        </div>
                    @endif
                </li>
            @empty<li class="list-group-item">@include('partials.empty', ['text' => 'Aucune demande en attente.'])</li>@endforelse
        </ul></div>
    </div>
</div>
@endsection
