@extends('layouts.app')
@section('title', $user->name)
@section('content')
@include('partials.head', ['title' => $user->name, 'crumbs' => ['Utilisateurs' => route('users.index')], 'sub' => e($user->username).($user->archived_at ? ' · archivé' : '')])
<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="{{ route('users.update', $user) }}" class="card mb-3">@csrf @method('PUT')
            <div class="card-header">Identité</div><div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label" for="n">Nom</label><input id="n" name="name" class="form-control" value="{{ $user->name }}" required></div>
                <div class="col-md-6"><label class="form-label" for="u">Identifiant</label><input id="u" name="username" class="form-control" value="{{ $user->username }}" required></div>
                <div class="col-md-6"><label class="form-label" for="e">E-mail</label><input id="e" type="email" name="email" class="form-control" value="{{ $user->email }}"></div>
                <div class="col-md-6"><label class="form-label" for="p">Téléphone</label><input id="p" name="phone" class="form-control" value="{{ $user->phone }}"></div>
                <div class="col-md-6"><label class="form-label" for="sm">Matricule (compte étudiant)</label><input id="sm" name="student_matricule" class="form-control" value="{{ $user->student?->matricule }}"></div>
                <div class="col-md-6 d-flex flex-column justify-content-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" @checked($user->is_active)><label class="form-check-label" for="ia">Actif</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="archive" value="1" id="ar"><label class="form-check-label" for="ar">Archiver (l’historique est conservé)</label></div></div>
            </div><div class="card-footer bg-white"><button class="btn btn-primary">Enregistrer</button></div></form>
        <div class="card"><div class="card-header">Récupération du compte</div><div class="card-body d-flex flex-wrap gap-2">
            <form method="post" action="{{ route('users.reset-password', $user) }}" data-confirm="Générer un mot de passe temporaire ? Vérifiez d’abord l’identité de la personne.">@csrf<input type="hidden" name="reason" value="Demande de l’utilisateur, identité vérifiée"><button class="btn btn-light">Mot de passe temporaire</button></form>
            @if($user->hasTwoFactor())<form method="post" action="{{ route('users.reset-2fa', $user) }}" data-confirm="Réinitialiser la double authentification de ce compte ?">@csrf<input type="hidden" name="reason" value="Perte du téléphone, identité vérifiée"><button class="btn btn-light">Réinitialiser la 2FA</button></form>@endif
        </div></div>
    </div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Rôles et périmètres</div>
        <ul class="list-group list-group-flush">@forelse($user->assignments as $a)
            <li class="list-group-item d-flex justify-content-between align-items-center"><span><strong>{{ $a->role->name }}</strong><br><span class="small text-muted-2">Faculté : {{ $a->faculty?->name ?? 'toutes' }} · Année : {{ $a->academicYear?->code ?? 'toutes' }}</span></span>
                <form method="post" action="{{ route('users.assignments.destroy', $a) }}" data-confirm="Retirer ce rôle ?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="Retirer">Retirer</button></form></li>
        @empty<li class="list-group-item text-muted-2 small">Aucune habilitation : le compte n’accède à aucun module.</li>@endforelse</ul>
        <form method="post" action="{{ route('users.assignments.store', $user) }}" class="card-body row g-2 border-top">@csrf
            <div class="col-md-4"><label class="form-label" for="ar1">Rôle</label><select id="ar1" name="role_id" class="form-select form-select-sm">@foreach($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="ar2">Faculté</label><select id="ar2" name="faculty_id" class="form-select form-select-sm"><option value="">Toutes</option>@foreach($faculties as $f)<option value="{{ $f->id }}">{{ $f->code }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="ar3">Année</label><select id="ar3" name="academic_year_id" class="form-select form-select-sm"><option value="">Toutes</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
            <div class="col-12"><button class="btn btn-sm btn-primary">Ajouter</button> <span class="small text-muted-2">Le cumul administration technique + approbations financières est refusé.</span></div>
        </form></div></div>
</div>
@endsection
