@extends('layouts.app')
@section('title', 'Décès')
@php $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; $u = auth()->user(); @endphp
@section('content')
@include('partials.head', ['title' => 'Registre des décès du personnel', 'sub' => 'Un décès s’enregistre depuis la fiche de l’agent et s’applique à sa validation par une autre personne. Les dossiers ne sont jamais effacés.'])
<div class="card">
@if($deaths->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Agent</th><th>Date</th><th>Lieu</th>@if($canCause)<th>Cause (confidentiel)</th>@endif<th>Contact</th><th>Situation financière au décès</th><th>Statut</th><th></th></tr></thead><tbody>
@foreach($deaths as $x)<tr>
    <td data-l="Agent"><a href="{{ route('employees.show', $x->employee) }}">{{ $x->employee->fullName() }}</a><br><span class="small text-muted-2">{{ $x->employee->matricule }} · {{ $x->employee->grade?->name }}</span></td>
    <td data-l="Date">{{ $d($x->death_date) }}</td><td data-l="Lieu">{{ $x->death_place }}</td>@if($canCause)<td data-l="Cause">{{ $x->cause ?: '—' }}</td>@endif
    <td data-l="Contact">{{ $x->contact_name }} {{ $x->contact_phone }}</td>
    <td data-l="Finances">@if($x->financial_snapshot)@foreach(\Illuminate\Support\Arr::except($x->financial_snapshot, ['_ajustements']) as $cur => $t)<div class="small">Dû aux ayants droit : <b>{{ money(\App\Support\Money::isPositive($t['balance']) ? $t['balance'] : 0, $cur) }}</b> @if(\App\Support\Money::isPositive($t['overpaid']))· trop-perçu {{ money($t['overpaid'], $cur) }}@endif</div>@endforeach @else — @endif</td>
    <td data-l="Statut">{!! \App\Support\Labels::badge('decision', $x->status) !!}<div class="mini text-muted-2">{{ $x->author?->name }} @if($x->validator)/ {{ $x->validator->name }}@endif</div></td>
    <td>@if($x->status === 'draft' && $u->canOnEmployee('deaths.validate', $x->employee))<form method="post" action="{{ route('deaths.validate', $x) }}" data-confirm="Valider le décès de {{ $x->employee->fullName() }} ? Statut, affectation et primes seront mis à jour.">@csrf<button class="btn btn-sm btn-primary">Valider</button></form>@endif</td>
</tr>@endforeach</tbody></table></div>@else <div class="card-body">@include('partials.empty', ['icon' => 'bi-flower1', 'text' => 'Aucun décès enregistré.'])</div> @endif
</div>
@endsection
