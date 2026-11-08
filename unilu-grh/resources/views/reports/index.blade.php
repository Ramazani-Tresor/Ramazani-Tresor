@extends('layouts.app')
@section('title', 'Éditions')
@section('content')
@include('partials.head', ['title' => 'Éditions et rapports', 'sub' => 'Documents officiels avec en-tête institutionnel, référence, date et espaces de signature — en PDF, Excel ou aperçu. Votre périmètre est appliqué.'])
<form method="get" id="rf" class="card mb-3"><div class="card-body filters">
    <div><label class="form-label" for="ry">Année académique</label><select id="ry" name="year" class="form-select"><option value="">Année en cours</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
    <div><label class="form-label" for="ru">Faculté / service</label><select id="ru" name="unit" class="form-select"><option value="">Toute l’université</option>@foreach($units as $un)<option value="{{ $un->id }}">{{ $un->name }}</option>@endforeach</select></div>
    <div><label class="form-label" for="rfm">Famille</label><select id="rfm" name="family" class="form-select"><option value="">Toutes</option>@foreach(\App\Support\PermissionRegistry::FAMILIES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    <div><label class="form-label" for="rs">Situation</label><select id="rs" name="status" class="form-select"><option value="">Effectif en fonction</option>@foreach(\App\Models\Employee::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
</div></form>
<div class="row g-3">
@foreach($types as $group => $list)
<div class="col-lg-6"><div class="card h-100"><div class="card-header">{{ $group }}</div><ul class="list-group list-group-flush">
    @foreach($list as $type => [$label])
    <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap"><span>{{ $label }}</span><span class="d-flex gap-1">
        <button form="rf" formaction="{{ route('reports.export', [$type, 'html']) }}" class="btn btn-sm btn-light" title="Aperçu"><i class="bi bi-eye"></i></button>
        <button form="rf" formaction="{{ route('reports.export', [$type, 'pdf']) }}" class="btn btn-sm btn-light" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
        <button form="rf" formaction="{{ route('reports.export', [$type, 'xlsx']) }}" class="btn btn-sm btn-light" title="Excel"><i class="bi bi-file-earmark-excel"></i> Excel</button>
    </span></li>
    @endforeach
</ul></div></div>
@endforeach
</div>
@endsection
