<div>
    <div class="card mb-3"><div class="card-body">
        <div class="filters">
            <div style="flex:2 1 220px"><label class="form-label" for="f-q">Recherche</label><input id="f-q" type="search" class="form-control" placeholder="Matricule, nom, postnom, prénom" wire:model.live.debounce.350ms="search"></div>
            <div><label class="form-label" for="f-fam">Famille</label><select id="f-fam" class="form-select" wire:model.live="family"><option value="">Toutes</option>@foreach(\App\Support\PermissionRegistry::FAMILIES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="form-label" for="f-gr">Grade</label><select id="f-gr" class="form-select" wire:model.live="grade"><option value="">Tous</option>@foreach($grades as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
            <div><label class="form-label" for="f-cat">Catégorie</label><select id="f-cat" class="form-select" wire:model.live="category"><option value="">Toutes</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="form-label" for="f-unit">Faculté / service</label><select id="f-unit" class="form-select" wire:model.live="unit"><option value="">Toutes</option>@foreach($units as $un)<option value="{{ $un->id }}">{{ $un->name }}</option>@endforeach</select></div>
            <div><label class="form-label" for="f-st">Situation</label><select id="f-st" class="form-select" wire:model.live="status"><option value="current">Effectif (en fonction)</option><option value="all">Tous, y compris partis</option>@foreach(\App\Models\Employee::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <button type="button" class="btn btn-light" wire:click="resetFilters"><i class="bi bi-x-lg"></i> Réinitialiser</button>
        </div>
    </div></div>
    <div class="card">
        <div class="card-header"><span>{{ $employees->total() }} agent(s)</span><span wire:loading class="small text-muted-2"><span class="spinner-border spinner-border-sm"></span> Actualisation…</span></div>
        @if($employees->count())
        <div class="table-responsive"><table class="table table-hover align-middle table-stack">
            <thead><tr><th>Matricule</th><th>Nom, postnom, prénom</th>@if($full)<th>Famille</th><th>Grade</th>@endif<th>Affectation</th>@if($full)<th>Situation</th>@endif</tr></thead>
            <tbody>
            @foreach($employees as $e)
                <tr wire:key="e{{ $e->id }}">
                    <td data-l="Matricule" class="nw">@if($full)<a href="{{ route('employees.show', $e) }}" class="fw-600">{{ $e->matricule }}</a>@else{{ $e->matricule }}@endif</td>
                    <td data-l="Nom">{{ $e->fullName() }}</td>
                    @if($full)<td data-l="Famille" class="small">{{ \App\Support\Labels::status('family', $e->staff_family) }}</td><td data-l="Grade">{{ $e->grade?->name ?? '—' }}</td>@endif
                    <td data-l="Affectation" class="small">{{ $e->activeAssignment?->orgUnit?->name ?? '—' }}</td>
                    @if($full)<td data-l="Situation">{!! \App\Support\Labels::badge('employee', $e->status) !!}</td>@endif
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-body border-top">{{ $employees->links() }}</div>
        @else
            @include('partials.empty', ['icon' => 'bi-people', 'text' => 'Aucun agent ne correspond à ces critères dans votre périmètre.'])
        @endif
    </div>
</div>
