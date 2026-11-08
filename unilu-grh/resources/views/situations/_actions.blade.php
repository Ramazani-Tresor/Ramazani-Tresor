@php $u = auth()->user(); @endphp
@if($s->status === 'requested' && $u->canOnEmployee('situations.validate', $s->employee))
    <form method="post" action="{{ route('situations.approve', $s) }}" class="d-inline" data-confirm="Approuver « {{ $s->type->name }} » ? Statut et primes seront ajustés selon les règles.">@csrf<button class="btn btn-sm btn-primary">Approuver</button></form>
    <form method="post" action="{{ route('situations.reject', $s) }}" class="d-inline-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif du rejet" style="max-width:130px"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>
@elseif($s->status === 'approved' && ! $s->type->ends_employment)
    @if($u->canOnEmployee('situations.validate', $s->employee))
        <form method="post" action="{{ route('situations.close', $s) }}" class="d-inline-flex gap-1" data-confirm="Clôturer et réintégrer l’agent en activité ?">@csrf<input type="date" name="end_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required aria-label="Date de fin effective"><button class="btn btn-sm btn-outline-primary">Clôturer / réintégrer</button></form>
    @endif
    @if($s->planned_end_date && $u->canOnEmployee('situations.manage', $s->employee))
        <form method="post" action="{{ route('situations.extend', $s) }}" class="d-inline-flex gap-1 mt-1">@csrf<input type="date" name="new_end" class="form-control form-control-sm" required aria-label="Nouvelle échéance"><input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif de prolongation" style="max-width:120px"><button class="btn btn-sm btn-light">Prolonger</button></form>
    @endif
@endif
