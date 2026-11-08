@extends('layouts.app')
@section('title', 'Réductions et exonérations')
@section('content')
@include('partials.head', ['title' => 'Bourses, réductions, exonérations, prises en charge', 'sub' => 'Les demandes se font depuis le dossier étudiant (icône % sur une créance). Approbation par une personne différente du demandeur.'])
<div class="card">
    <div class="card-body border-bottom d-flex flex-wrap gap-2 align-items-end">
        @include('partials.filters', ['show' => ['year', 'faculty']])
        <div class="btn-group ms-auto" role="group" aria-label="Statut">@foreach(['pending' => 'En attente', 'approved' => 'Approuvés', 'rejected' => 'Rejetés', 'all' => 'Tous'] as $k => $l)<a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-light' }}" href="{{ request()->fullUrlWithQuery(['status' => $k]) }}">{{ $l }}</a>@endforeach</div>
    </div>
    <div class="table-responsive"><table class="table table-stack">
        <thead><tr><th>N°</th><th>Étudiant</th><th>Frais</th><th>Type</th><th class="num">Montant</th><th>Motif</th><th>Justificatif</th><th>Demandé par</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $a)
            <tr>
                <td data-l="N°" class="small">{{ $a->number }}</td>
                <td data-l="Étudiant"><a href="{{ route('students.show', $a->charge->student) }}">{{ $a->charge->student->matricule }}</a><div class="small">{{ $a->charge->student->fullName() }}</div></td>
                <td data-l="Frais">{{ $a->charge->category->label }}</td>
                <td data-l="Type">{{ status_label('adjustment_type', $a->type) }}{{ $a->sponsor ? ' — '.$a->sponsor : '' }}</td>
                <td data-l="Montant" class="num">{{ money(ltrim($a->amount, '-'), $a->charge->currency) }}{{ str_starts_with($a->amount, '-') ? ' ↑' : '' }}</td>
                <td data-l="Motif" class="small">{{ $a->reason }}</td>
                <td data-l="Justificatif">@if($a->justification_path)<a href="{{ route('adjustments.justification', $a) }}">Ouvrir</a>@else — @endif</td>
                <td data-l="Demandé par" class="small">{{ $a->requester->name }}<br>{{ $a->created_at->format('d/m/Y') }}</td>
                <td data-l="Statut">{!! \App\Support\Labels::badge('adjustment', $a->status) !!}@if($a->decision_note)<div class="small">{{ $a->decision_note }}</div>@endif</td>
                <td class="nw">
                    @if($a->status === 'pending' && can_do('adjustments.approve') && $a->requested_by !== auth()->id())
                        <form method="post" action="{{ route('adjustments.approve', $a) }}" class="d-inline" data-confirm="Approuver cet ajustement ? Le solde de la créance diminuera.">@csrf<button class="btn btn-sm btn-accent">Approuver</button></form>
                        <form method="post" action="{{ route('adjustments.reject', $a) }}" class="d-inline-flex gap-1 mt-1">@csrf<input name="note" class="form-control form-control-sm" placeholder="Motif du rejet" required aria-label="Motif du rejet"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>
                    @elseif($a->status === 'pending')<span class="small text-muted-2">En attente d’un autre approbateur</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10">@include('partials.empty', ['text' => 'Aucune demande dans cet état.'])</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $items->links() }}</div>
</div>
@endsection
