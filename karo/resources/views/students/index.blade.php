@extends('layouts.app')
@section('title', 'Étudiants')
@section('content')
@include('partials.head', ['title' => 'Étudiants', 'sub' => $students->total().' dossier(s) · année '.e($filters->year?->code ?? '—'),
    'actions' => (can_do('students.import') ? '<a class="btn btn-light" href="'.route('imports.students.create').'"><i class="bi bi-file-earmark-spreadsheet"></i> Import Excel</a>' : '').(can_do('students.manage') ? '<a class="btn btn-primary" href="'.route('students.create').'"><i class="bi bi-person-plus"></i> Nouveau dossier</a>' : '')])
<div class="card">
    <div class="card-body border-bottom">@include('partials.filters', ['show' => ['q', 'year', 'faculty', 'promotion']])</div>
    <div class="table-responsive"><table class="table table-hover table-stack">
        <thead><tr><th>Matricule</th><th>Nom complet</th><th>Promotion ({{ $filters->year?->code }})</th><th>Statut</th><th>Contact</th><th></th></tr></thead>
        <tbody>
        @forelse($students as $s)
            @php $e = $s->enrollments->first(); @endphp
            <tr>
                <td data-l="Matricule"><a href="{{ route('students.show', $s) }}" class="fw-600">{{ $s->matricule }}</a></td>
                <td data-l="Nom">{{ $s->fullName() }}</td>
                <td data-l="Promotion">{{ $e?->promotion->code ?? '—' }}</td>
                <td data-l="Statut">{{ $e ? status_label('enrollment_status', $e->status) : 'Non inscrit' }}</td>
                <td data-l="Contact" class="small">{{ $s->phone }}</td>
                <td class="text-end"><a href="{{ route('students.show', $s) }}" class="btn btn-sm btn-light" aria-label="Ouvrir le dossier de {{ $s->fullName() }}"><i class="bi bi-chevron-right"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="6">@include('partials.empty', ['icon' => 'bi-people', 'text' => 'Aucun étudiant ne correspond à ces critères.'])</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $students->links() }}</div>
</div>
@endsection
