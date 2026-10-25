@extends('layouts.app')
@section('title', 'Mon compte')
@section('content')
<div class="page-head"><div><h1>Mon compte</h1><div class="sub">{{ $user->name }} · {{ $user->role->name }}</div></div></div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Changer mon mot de passe</div>
            <form class="card-body" method="post" action="{{ route('account.password') }}">@csrf @method('PUT')
                <div class="mb-3"><label class="form-label">Mot de passe actuel</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label">Nouveau mot de passe</label><input type="password" name="password" class="form-control" required autocomplete="new-password"><div class="form-text">8 caractères minimum, avec lettres et chiffres.</div></div>
                <div class="mb-3"><label class="form-label">Confirmation</label><input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
                <button class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Mes permissions</div>
            <div class="card-body small">
                @if($user->isAdmin())<p class="mb-0">Administrateur : toutes les permissions.</p>@else
                    <ul class="mb-0">@foreach($user->role->permissions as $p)<li>{{ $p->name }}</li>@endforeach</ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
