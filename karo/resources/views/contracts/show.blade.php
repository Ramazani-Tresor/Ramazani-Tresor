@extends('layouts.app')
@section('title', 'Contrat '.$c->number)
@section('content')
@include('partials.head', ['title' => 'Contrat '.$c->number.' — version '.$c->version, 'crumbs' => ['Contrats' => route('contracts.index'), 'Étudiant' => route('students.show', $c->student)],
    'sub' => \App\Support\Labels::badge('contract', $c->status).' · '.e($c->student->matricule).' — '.e($c->student->fullName()).' · '.e($c->academicYear->code),
    'actions' => '<a class="btn btn-light" target="_blank" rel="noopener" href="'.route('contracts.pdf', $c).'"><i class="bi bi-printer"></i> Imprimer</a><a class="btn btn-light" href="'.route('contracts.pdf', [$c, 'download' => 1]).'"><i class="bi bi-file-earmark-pdf"></i> PDF</a>'])
@if($cp['breached'])<div class="alert alert-danger">Contrat non respecté : {{ money($cp['due_so_far'], $c->currency) }} échus, {{ money($cp['paid_since'], $c->currency) }} payés depuis la signature (manque {{ money($cp['shortfall'], $c->currency) }}).</div>@endif
<div class="row g-3">
    <div class="col-lg-8"><div class="card mb-3"><div class="card-body"><dl class="dl-grid">
        <div><dt>Total dû (net)</dt><dd>{{ money($c->total_due, $c->currency) }}</dd></div><div><dt>Payé à la signature</dt><dd>{{ money($c->paid_at_signature, $c->currency) }}</dd></div>
        <div><dt>Solde couvert</dt><dd class="fw-bold">{{ money($c->balance, $c->currency) }}</dd></div><div><dt>Préparé par</dt><dd>{{ $c->creator->name }}</dd></div>
        <div><dt>Approuvé par</dt><dd>{{ $c->approver?->name ?? '—' }}</dd></div><div><dt>Frais</dt><dd>{{ $charges->pluck('category.label')->join(', ') }}</dd></div>
    </dl></div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>#</th><th>Date</th><th class="num">Montant</th></tr></thead><tbody>@foreach($c->installments as $i)<tr class="{{ $i->due_date->isPast() ? 'table-light' : '' }}"><td>{{ $i->sequence }}</td><td>{{ $i->due_date->format('d/m/Y') }}</td><td class="num">{{ money($i->amount, $c->currency) }}</td></tr>@endforeach</tbody></table></div>
    <div class="card-body small"><strong>Conditions :</strong> {{ $c->conditions }}</div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
            @if($c->status === 'draft' && can_do('contracts.approve'))@if($c->created_by === auth()->id())<p class="small text-muted-2 mb-0">L’approbation revient à une autre personne.</p>@else<form method="post" action="{{ route('contracts.approve', $c) }}" data-confirm="Approuver ce contrat ?">@csrf<button class="btn btn-accent w-100">Approuver</button></form>@endif @endif
            @if($c->status === 'active' && can_do('contracts.approve'))<form method="post" action="{{ route('contracts.archive', $c) }}" enctype="multipart/form-data" class="d-grid gap-1">@csrf<label class="form-label" for="sf">Version signée (PDF/image)</label><input id="sf" type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required><button class="btn btn-light btn-sm">Archiver la version signée</button></form>@endif
            @if($c->signed_file_path)<a class="btn btn-light" href="{{ route('contracts.signed', $c) }}"><i class="bi bi-paperclip"></i> Version signée</a>@endif
            @if(in_array($c->status, ['draft', 'active', 'signed']) && can_do('contracts.manage') && $c->academicYear->status === 'open')<a class="btn btn-light" href="{{ route('contracts.version', $c) }}"><i class="bi bi-files"></i> Nouvelle version</a>@endif
        </div></div>
        <div class="card"><div class="card-header">Versions</div><ul class="list-group list-group-flush">@foreach($versions as $v)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('contracts.show', $v) }}">v{{ $v->version }} — {{ $v->created_at->format('d/m/Y') }}</a>{!! \App\Support\Labels::badge('contract', $v->status) !!}</li>@endforeach</ul></div>
    </div>
</div>
@endsection
