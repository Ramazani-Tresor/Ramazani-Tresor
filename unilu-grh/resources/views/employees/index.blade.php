@extends('layouts.app')
@section('title', 'Agents')
@section('content')
@include('partials.head', ['title' => 'Fichier du personnel', 'sub' => 'Personnel académique, scientifique, administratif et ouvrier — recherche et filtres en direct, limités à votre périmètre.', 'actions' => (can_do('employees.manage') ? '<a class="btn btn-primary" href="'.route('employees.create').'"><i class="bi bi-person-plus"></i> Nouveau dossier</a>' : '').(can_do('reports.view') ? '<a class="btn btn-light" href="'.route('reports.index').'"><i class="bi bi-printer"></i> Listes</a>' : '')])
<livewire:employee-directory />
@endsection
