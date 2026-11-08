@extends('layouts.app')
@section('title', 'Paiement '.$payment->number)
@section('content')
@php $me = auth()->id(); $editable = in_array($payment->status, ['submitted', 'checked']) && $payment->academicYear->status === 'open'; @endphp
@include('partials.head', ['title' => 'Paiement '.$payment->number, 'crumbs' => ['Paiements' => route('payments.index')],
    'sub' => \App\Support\Labels::badge('payment', $payment->status).' '.\App\Support\Labels::badge('bank', $payment->bank_status).' · <a href="'.route('students.show', $payment->student).'">'.e($payment->student->matricule).' — '.e($payment->student->fullName()).'</a> · '.e($payment->academicYear->code)])

@if($liveAnomalies)
    <div class="alert alert-danger"><div class="fw-600"><i class="bi bi-exclamation-triangle-fill"></i> Anomalies détectées (à examiner avant validation)</div><ul class="mb-0">@foreach($liveAnomalies as $a)<li>{{ $a['message'] }}</li>@endforeach</ul></div>
@elseif($payment->anomalies && $payment->status === 'validated')
    <div class="alert alert-warning small mb-3">Validé malgré les anomalies suivantes, examinées par le validateur : {{ collect($payment->anomalies)->pluck('message')->join(' ; ') }}</div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-header">Bordereau</div><div class="card-body">
            <dl class="dl-grid">
                <div><dt>Type</dt><dd>{{ $payment->type === 'carry_forward' ? 'Report d’excédent (pas un versement bancaire)' : 'Versement bancaire' }}</dd></div>
                <div><dt>Banque / compte</dt><dd>{{ $payment->bankAccount?->display() ?? '—' }}</dd></div>
                <div><dt>Référence bancaire</dt><dd>{{ $payment->bank_reference ?? '—' }}</dd></div>
                <div><dt>Date du versement</dt><dd>{{ $payment->deposit_date?->format('d/m/Y') }}</dd></div>
                <div><dt>Montant</dt><dd class="fs-5 fw-bold">{{ money($payment->amount, $payment->currency) }}</dd></div>
                <div><dt>Déposant</dt><dd>{{ $payment->depositor_name ?? '—' }}</dd></div>
                <div><dt>Affecté</dt><dd>{{ money($payment->allocatedAmount(), $payment->currency) }}</dd></div>
                <div><dt>Disponible (non affecté)</dt><dd>{{ money($payment->availableAmount(), $payment->currency) }}</dd></div>
                <div><dt>Confirmé par la banque</dt><dd>{{ money($payment->bank_confirmed_amount, $payment->currency) }}</dd></div>
                <div><dt>Justificatif</dt><dd>@if($payment->proof_path)<a href="{{ route('payments.proof', $payment) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Ouvrir</a>@else <span class="text-danger">Manquant</span>@endif</dd></div>
                <div><dt>Canal</dt><dd>{{ ['web' => 'Saisie au guichet', 'offline_sync' => 'Saisie hors connexion synchronisée ('.$payment->device_id.')', 'system' => 'Opération système'][$payment->channel] }}</dd></div>
                @if($payment->originPayment)<div><dt>Origine</dt><dd><a href="{{ route('payments.show', $payment->originPayment) }}">{{ $payment->originPayment->number }}</a></dd></div>@endif
            </dl>
        </div></div>

        <div class="card mb-3"><div class="card-header">Répartition (affectations) @if($editable && ($payment->created_by === $me || can_do('payments.check')))<span class="small fw-normal text-muted-2">modifiable jusqu’à la validation</span>@endif</div>
            @if($editable && ($payment->created_by === $me || can_do('payments.check')))
                <form method="post" action="{{ route('payments.allocations', $payment) }}">@csrf @method('PUT')
                    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Frais</th><th class="num">Solde dû (validé)</th><th class="num" style="width:170px">Affectation proposée</th></tr></thead><tbody>
                    @foreach($openCharges->where('currency', $payment->currency) as $c)
                        <tr><td><label for="pa{{ $c->id }}">{{ $c->category->label }}</label></td><td class="num">{{ money($c->balance(), $c->currency) }}</td>
                            <td><input id="pa{{ $c->id }}" name="allocations[{{ $c->id }}]" class="form-control form-control-sm text-end" inputmode="decimal" value="{{ optional($payment->allocations->where('status', 'proposed')->firstWhere('charge_id', $c->id))->amount }}"></td></tr>
                    @endforeach
                    </tbody></table></div>
                    <div class="card-body py-2 text-end"><button class="btn btn-sm btn-light">Enregistrer la répartition</button></div>
                </form>
            @else
                <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Frais</th><th>Nature</th><th class="num">Montant</th><th>Statut</th></tr></thead><tbody>
                @forelse($payment->allocations as $a)
                    <tr><td data-l="Frais">{{ $a->charge->category->label }} <span class="small text-muted-2">{{ $a->charge->number }}</span></td><td data-l="Nature">{{ $a->kind === 'compensation' ? 'Compensation' : 'Directe' }}</td>
                        <td data-l="Montant" class="num">{{ money($a->amount, $payment->currency) }}{{ $a->charge->currency !== $payment->currency ? ' = '.money($a->charge_amount, $a->charge->currency) : '' }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('allocation', $a->status) !!}</td></tr>
                @empty<tr><td colspan="4">@include('partials.empty', ['text' => 'Aucune affectation : montant conservé en crédit non affecté.'])</td></tr>@endforelse
                </tbody></table></div>
            @endif
        </div>

        <div class="card"><div class="card-header">Rapprochements bancaires</div>
            <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Ligne de relevé</th><th>Date opération</th><th class="num">Montant</th><th>Méthode</th><th>Statut</th></tr></thead><tbody>
            @forelse($payment->matches as $m)
                <tr><td data-l="Ligne"><a href="{{ route('bank.lines.show', $m->line) }}">#{{ $m->line->id }} {{ $m->line->reference }}</a></td><td data-l="Date">{{ $m->line->operation_date->format('d/m/Y') }}</td><td data-l="Montant" class="num">{{ money($m->amount, $payment->currency) }}</td><td data-l="Méthode">{{ $m->method === 'auto_exact' ? 'Automatique (exacte)' : 'Manuelle' }}</td><td data-l="Statut">{{ $m->status === 'active' ? 'Actif' : 'Annulé' }}</td></tr>
            @empty<tr><td colspan="5">@include('partials.empty', ['text' => 'Pas encore rapproché avec un relevé bancaire.'])</td></tr>@endforelse
            </tbody></table></div></div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3 sticky-sum"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
            @if($payment->status === 'submitted' && can_do('payments.check'))
                @if($payment->created_by === $me)<p class="small text-muted-2 mb-0">Vous avez saisi ce bordereau : le contrôle revient à une autre personne.</p>
                @else<form method="post" action="{{ route('payments.check', $payment) }}" class="d-grid gap-2">@csrf<input name="note" class="form-control form-control-sm" placeholder="Observation (facultatif)" aria-label="Observation"><button class="btn btn-primary" @disabled(!$payment->proof_path)><i class="bi bi-check2"></i> Justificatif contrôlé et conforme</button></form>@endif
            @endif
            @if(in_array($payment->status, (setting('require_check_before_validation') === '1' ? ['checked'] : ['submitted', 'checked'])) && can_do('payments.validate'))
                @if($payment->created_by === $me)<p class="small text-muted-2 mb-0">Vous avez saisi ce bordereau : la validation revient à une autre personne.</p>
                @else<form method="post" action="{{ route('payments.validate', $payment) }}" class="d-grid gap-2" data-confirm="Valider ce paiement ? Les affectations deviennent effectives.">@csrf
                    @if($liveAnomalies)<div class="form-check"><input class="form-check-input" type="checkbox" name="acknowledge" value="1" id="ack" required><label class="form-check-label small" for="ack">J’ai examiné les anomalies signalées et je les accepte</label></div>@endif
                    <input name="note" class="form-control form-control-sm" placeholder="Commentaire (facultatif)" aria-label="Commentaire"><button class="btn btn-accent"><i class="bi bi-patch-check"></i> Valider</button></form>@endif
            @endif
            @if(in_array($payment->status, ['submitted', 'checked']) && (can_do('payments.check') || can_do('payments.validate')))
                <form method="post" action="{{ route('payments.reject', $payment) }}" class="d-grid gap-2" data-confirm="Rejeter ce bordereau ?">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif du rejet" required aria-label="Motif du rejet"><button class="btn btn-outline-danger">Rejeter</button></form>
            @endif
            @if($payment->status === 'validated')
                @if(can_do('documents.issue') && $payment->type === 'bank_deposit')<form method="post" action="{{ route('documents.issue', $payment->student) }}">@csrf<input type="hidden" name="type" value="receipt"><input type="hidden" name="payment_id" value="{{ $payment->id }}"><button class="btn btn-light w-100"><i class="bi bi-receipt"></i> Émettre le reçu</button></form>@endif
                @if(\App\Support\Money::isPositive($payment->availableAmount()) && can_do('credits.request'))<a class="btn btn-light" href="{{ route('credits.index', ['payment' => $payment->id]) }}"><i class="bi bi-arrow-left-right"></i> Utiliser l’excédent</a>@endif
                @if(can_do('payments.reverse') && $payment->academicYear->status === 'open')<form method="post" action="{{ route('payments.reverse', $payment) }}" class="d-grid gap-2 mt-2" data-confirm="Contrepasser ce paiement ? Ses affectations seront annulées (sans suppression).">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif de la contrepassation" required aria-label="Motif"><button class="btn btn-outline-danger btn-sm">Contrepasser</button></form>@endif
            @endif
            @if(in_array($payment->status, ['rejected', 'reversed']))<p class="mb-0 small">{{ $payment->status === 'rejected' ? 'Rejeté : '.$payment->rejection_reason : 'Contrepassé : '.$payment->reversal_reason }}</p>@endif
        </div></div>

        <div class="card"><div class="card-header">Historique</div><ul class="list-group list-group-flush small">
            @foreach($payment->events as $e)<li class="list-group-item"><strong>{{ status_label('payment', $e->to_status) }}</strong> — {{ $e->user->name }}<br><span class="text-muted-2">{{ $e->created_at->format('d/m/Y H:i') }}</span>@if($e->note)<br>{{ $e->note }}@endif</li>@endforeach
        </ul></div>
    </div>
</div>
@endsection
