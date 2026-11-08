@extends('layouts.app')
@section('title', 'Dépense '.$expense->number)
@section('content')
<div class="page-head no-print">
    <div><h1>Dépense {{ $expense->number }} @include('expenses._status')</h1></div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer le bon de dépense</button>
        @if($expense->attachment_path)<a href="{{ route('expenses.attachment', $expense) }}" class="btn btn-light"><i class="bi bi-paperclip me-1"></i>Justificatif</a>@endif
        @if($expense->status === 'pending' && can_do('expenses.approve'))
            <form method="post" action="{{ route('expenses.approve', $expense) }}" data-confirm="Valider cette dépense ?{{ $expense->paid_from_cash ? ' Le montant sera décaissé de VOTRE caisse ouverte.' : '' }}">@csrf<button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Valider</button></form>
            <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectBox">Rejeter</button>
        @endif
    </div>
</div>
<div class="collapse mb-3" id="rejectBox"><div class="card card-body" style="max-width:520px">
    <form method="post" action="{{ route('expenses.reject', $expense) }}">@csrf<label class="form-label">Motif du rejet *</label><input name="rejection_reason" class="form-control mb-2" required maxlength="255"><button class="btn btn-danger">Confirmer le rejet</button></form>
</div></div>
<div class="card" style="max-width:820px"><div class="card-body">
    <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
        <div><div class="fw-bold fs-5 text-navy">{{ $shop['shop_name'] }}</div><div class="small">{{ $shop['shop_address'] }}</div></div>
        <div class="text-end"><div class="fw-bold">BON DE DÉPENSE</div><div>{{ $expense->number }}</div></div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Date</dt><dd class="col-sm-8">{{ $expense->expense_date->format('d/m/Y') }}</dd>
        <dt class="col-sm-4">Catégorie</dt><dd class="col-sm-8">{{ $expense->category->name }}</dd>
        <dt class="col-sm-4">Bénéficiaire</dt><dd class="col-sm-8">{{ $expense->beneficiary }}</dd>
        <dt class="col-sm-4">Motif</dt><dd class="col-sm-8">{{ $expense->reason }}</dd>
        <dt class="col-sm-4">Montant</dt><dd class="col-sm-8 fs-5 fw-bold">{{ money($expense->amount) }}</dd>
        <dt class="col-sm-4">Mode de paiement</dt><dd class="col-sm-8">{{ $expense->method->name }}{{ $expense->paid_from_cash ? ' — payée depuis la caisse' : '' }}</dd>
        <dt class="col-sm-4">Saisie par</dt><dd class="col-sm-8">{{ $expense->user->name }} le {{ $expense->created_at->format('d/m/Y H:i') }}</dd>
        @if($expense->approver)<dt class="col-sm-4">{{ $expense->status === 'rejected' ? 'Rejetée' : 'Validée' }} par</dt><dd class="col-sm-8">{{ $expense->approver->name }} le {{ $expense->approved_at->format('d/m/Y H:i') }}{{ $expense->rejection_reason ? ' — '.$expense->rejection_reason : '' }}</dd>@endif
    </dl>
    <div class="row mt-5 text-center small"><div class="col">Le bénéficiaire<br><br>________________</div><div class="col">Le caissier<br><br>________________</div><div class="col">Le responsable<br><br>________________</div></div>
</div></div>
@endsection
