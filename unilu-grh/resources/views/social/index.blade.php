@extends('layouts.app')
@section('title', 'Soutiens sociaux')
@php $u = auth()->user(); $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; @endphp
@section('content')
@include('partials.head', ['title' => 'Soutiens sociaux', 'sub' => 'Assistance en cas de décès (montant selon le degré de parenté), de maladie et aides exceptionnelles. Circuit : demande → approbation par une autre personne → paiement.'])
<div class="row g-3">
<div class="col-xl-8">
@foreach([['Demandes à approuver', $requested, 'requested'], ['Approuvés, à payer', $approved, 'approved'], ['Payés ou rejetés (récents)', $done, 'done']] as [$title, $list, $kind])
<div class="card mb-3"><div class="card-header">{{ $title }} ({{ $list->count() }})</div>
@if($list->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>N°</th><th>Agent</th><th>Type</th><th>Objet</th><th class="num">Montant</th><th>Statut</th><th></th></tr></thead><tbody>
@foreach($list as $s)<tr>
    <td data-l="N°" class="nw">{{ $s->number }}<br><span class="mini text-muted-2">{{ $s->created_at->format('d/m/Y') }}</span></td><td data-l="Agent"><a href="{{ route('employees.show', $s->employee) }}#finances">{{ $s->employee->fullName() }}</a></td><td data-l="Type">{{ $s->type->name }}</td>
    <td data-l="Objet">@if($s->deceased_name){{ $s->deceased_name }} ({{ $s->kinship?->name }}) — {{ $d($s->event_date) }}@else{{ \Illuminate\Support\Str::limit($s->description, 60) }}@endif @if($s->beneficiary)<br><span class="small">Bénéficiaire : {{ $s->beneficiary }}</span>@endif @foreach($s->documents as $doc)<a href="{{ route('documents.download', $doc) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endforeach</td>
    <td data-l="Montant" class="num">{{ $s->amount_granted !== null ? money($s->amount_granted, $s->currency) : ($s->amount_requested !== null ? money($s->amount_requested, $s->currency).' (demandé)' : '—') }}</td>
    <td data-l="Statut">{!! \App\Support\Labels::badge('support', $s->status) !!} @if($s->decision_reference)<br><span class="mini">{{ $s->decision_reference }}</span>@endif @if($s->payment_reference)<br><span class="mini">{{ $s->payment_reference }} · {{ $d($s->paid_on) }}</span>@endif</td>
    <td class="nw">
        @if($kind === 'requested' && $u->canOnEmployee('social.approve', $s->employee))
            <form method="post" action="{{ route('social.approve', $s) }}" class="d-flex gap-1 mb-1">@csrf<input name="amount_granted" type="number" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="{{ $s->amount_requested }}" aria-label="Montant accordé" style="max-width:100px"><input name="decision_reference" class="form-control form-control-sm" placeholder="Décision" required aria-label="Référence de décision" style="max-width:110px"><button class="btn btn-sm btn-primary">Approuver</button></form>
            <form method="post" action="{{ route('social.reject', $s) }}" class="d-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif" style="max-width:210px"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>
        @elseif($kind === 'approved' && $u->hasPermission('social.pay'))
            <form method="post" action="{{ route('social.pay', $s) }}" class="d-flex gap-1">@csrf<input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required aria-label="Date de paiement"><input name="payment_reference" class="form-control form-control-sm" placeholder="Référence" required aria-label="Référence de paiement" style="max-width:110px"><button class="btn btn-sm btn-primary">Payé</button></form>
        @endif
    </td>
</tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucun.</div> @endif
</div>
@endforeach
</div>
<div class="col-xl-4">
@if($u->hasPermission('social.manage'))
<div class="card"><div class="card-header">Nouvelle demande</div><div class="card-body"><form method="post" action="{{ route('social.store') }}" enctype="multipart/form-data" class="row g-2">@csrf
    @include('partials.field', ['name' => 'matricule', 'label' => 'Matricule de l’agent', 'required' => true, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'support_type_id', 'label' => 'Type de soutien', 'type' => 'select', 'required' => true, 'options' => $types->pluck('name', 'id')->all(), 'col' => 'col-12'])
    @include('partials.field', ['name' => 'kinship_degree_id', 'label' => 'Lien de parenté (deuil)', 'type' => 'select', 'options' => $kinships->mapWithKeys(fn ($k) => [$k->id => $k->name.' — '.money($k->support_amount, $k->currency)])->all(), 'col' => 'col-12'])
    @include('partials.field', ['name' => 'deceased_name', 'label' => 'Personne décédée', 'col' => 'col-md-7'])
    @include('partials.field', ['name' => 'event_date', 'label' => 'Date du décès / de l’événement', 'type' => 'date', 'col' => 'col-md-5'])
    @include('partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'col' => 'col-12'])
    @include('partials.field', ['name' => 'amount_requested', 'label' => 'Montant demandé', 'type' => 'decimal', 'col' => 'col-md-6', 'help' => 'Vide = montant paramétré.'])
    @include('partials.field', ['name' => 'beneficiary', 'label' => 'Bénéficiaire', 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'document', 'label' => 'Pièce justificative', 'type' => 'file', 'col' => 'col-12'])
    <div class="col-12"><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>
@endif
</div>
</div>
@endsection
