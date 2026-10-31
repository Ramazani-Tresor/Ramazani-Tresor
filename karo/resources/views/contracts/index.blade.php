@extends('layouts.app')
@section('title', 'Contrats')
@section('content')
@include('partials.head', ['title' => 'Contrats et échéanciers', 'sub' => 'Un contrat se crée depuis le dossier de l’étudiant. Toute modification crée une nouvelle version.'])
<div class="card">
    <div class="card-body border-bottom d-flex gap-2 flex-wrap"><a class="btn btn-sm {{ !request('breached') && !request('all') ? 'btn-primary' : 'btn-light' }}" href="{{ route('contracts.index') }}">En vigueur</a><a class="btn btn-sm {{ request('breached') ? 'btn-primary' : 'btn-light' }}" href="{{ route('contracts.index', ['breached' => 1]) }}">Non respectés</a><a class="btn btn-sm {{ request('all') ? 'btn-primary' : 'btn-light' }}" href="{{ route('contracts.index', ['all' => 1]) }}">Toutes les versions</a></div>
    <div class="table-responsive"><table class="table table-hover table-stack mb-0"><thead><tr><th>Contrat</th><th>Étudiant</th><th>Année</th><th class="num">Solde couvert</th><th class="num">Échu à ce jour</th><th class="num">Payé depuis</th><th>Respect</th><th>Statut</th></tr></thead><tbody>
    @forelse($rows as $r)
        @php $c = $r['c']; $cp = $r['cp']; @endphp
        <tr><td data-l="Contrat"><a href="{{ route('contracts.show', $c) }}">{{ $c->number }}</a> v{{ $c->version }}</td><td data-l="Étudiant">{{ $c->student->matricule }}<div class="small">{{ $c->student->fullName() }}</div></td><td data-l="Année">{{ $c->academicYear->code }}</td>
            <td data-l="Solde" class="num">{{ money($c->balance, $c->currency) }}</td><td data-l="Échu" class="num">{{ money($cp['due_so_far'], $c->currency) }}</td><td data-l="Payé" class="num">{{ money($cp['paid_since'], $c->currency) }}</td>
            <td data-l="Respect">@if($cp['breached'])<span class="badge-st st-danger">Retard {{ money($cp['shortfall'], $c->currency) }}</span>@elseif(in_array($c->status, ['active', 'signed']))<span class="badge-st st-ok">Respecté</span>@else — @endif</td>
            <td data-l="Statut">{!! \App\Support\Labels::badge('contract', $c->status) !!}</td></tr>
    @empty<tr><td colspan="8">@include('partials.empty', ['icon' => 'bi-file-earmark-text', 'text' => 'Aucun contrat.'])</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
