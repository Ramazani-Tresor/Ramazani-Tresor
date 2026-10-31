@extends('layouts.app')
@section('title', 'Barème')
@section('content')
@include('partials.head', ['title' => $s->category->label.' — v'.$s->version, 'crumbs' => ['Frais' => route('fees.index', ['year' => $s->academic_year_id])], 'sub' => \App\Support\Labels::badge('schedule', $s->status).' · '.e($s->academicYear->code).' · '.e($s->criteriaLabel())])
<div class="row g-3">
    <div class="col-lg-7"><div class="card mb-3"><div class="card-body"><dl class="dl-grid">
        <div><dt>Montant</dt><dd class="fs-5 fw-bold">{{ money($s->amount, $s->currency) }}</dd></div><div><dt>Date d’effet</dt><dd>{{ $s->effective_from->format('d/m/Y') }}</dd></div>
        <div><dt>Préparé par</dt><dd>{{ $s->creator->name }}, {{ $s->created_at->format('d/m/Y H:i') }}</dd></div><div><dt>Décision</dt><dd>{{ $s->approver ? $s->approver->name.', '.$s->approved_at->format('d/m/Y H:i') : '—' }}</dd></div>
        @if($s->change_reason)<div><dt>Motif de modification</dt><dd>{{ $s->change_reason }}</dd></div><div><dt>Créances existantes</dt><dd>{{ $s->apply_to_existing ? 'Révisées' : 'Inchangées' }}</dd></div>@endif
        <div><dt>Créances établies</dt><dd>{{ $chargesCount }}</dd></div>
    </dl></div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Tranche</th><th>Saisie</th><th class="num">Montant</th><th>Date limite</th></tr></thead><tbody>
        @foreach($s->installments as $i)<tr><td>{{ $i->label }}</td><td>{{ $i->mode === 'percent' ? str_replace('.', ',', rtrim(rtrim($i->value, '0'), '.')).' %' : 'montant' }}</td><td class="num">{{ money($i->amount, $s->currency) }}</td><td>{{ $i->due_date->format('d/m/Y') }}</td></tr>@endforeach
    </tbody></table></div></div></div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
            @if($s->status === 'draft' && can_do('fees.approve'))
                @if($s->created_by === auth()->id())<p class="small text-muted-2 mb-0">Vous avez préparé ce barème : son approbation revient à une autre personne.</p>
                @else<form method="post" action="{{ route('fees.schedules.approve', $s) }}" data-confirm="Approuver et activer ce barème ?">@csrf<button class="btn btn-accent w-100">Approuver et activer</button></form>
                    <form method="post" action="{{ route('fees.schedules.reject', $s) }}" class="d-flex gap-1">@csrf<input name="note" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif du rejet"><button class="btn btn-outline-danger btn-sm">Rejeter</button></form>@endif
            @endif
            @if($s->status === 'active' && can_do('fees.manage'))<a class="btn btn-light" href="{{ route('fees.schedules.create', ['replaces' => $s->id]) }}"><i class="bi bi-files"></i> Créer une nouvelle version</a>@endif
        </div></div>
        <div class="card"><div class="card-header">Versions</div><ul class="list-group list-group-flush">@foreach($versions as $v)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('fees.schedules.show', $v) }}">v{{ $v->version }} — {{ money($v->amount, $v->currency) }} dès le {{ $v->effective_from->format('d/m/Y') }}</a>{!! \App\Support\Labels::badge('schedule', $v->status) !!}</li>@endforeach</ul></div>
    </div>
</div>
@endsection
