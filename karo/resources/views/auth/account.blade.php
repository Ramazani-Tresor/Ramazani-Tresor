@extends('layouts.app')
@section('title', 'Mon compte')
@section('content')
@include('partials.head', ['title' => 'Mon compte et sécurité', 'sub' => e($user->name).' · '.e($user->roleNames())])
@if($user->must_change_password)<div class="alert alert-warning">Vous utilisez un mot de passe temporaire : choisissez-en un nouveau pour continuer.</div>@endif
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-header">Changer mon mot de passe</div>
        <form class="card-body" method="post" action="{{ route('account.password') }}">@csrf @method('PUT')
            <div class="mb-3"><label class="form-label" for="cp">Mot de passe actuel</label><input id="cp" type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
            <div class="mb-3"><label class="form-label" for="np">Nouveau mot de passe</label><input id="np" type="password" name="password" class="form-control" required autocomplete="new-password"><div class="form-text">10 caractères minimum, majuscules, minuscules et chiffres.</div></div>
            <div class="mb-3"><label class="form-label" for="np2">Confirmation</label><input id="np2" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
            <button class="btn btn-primary">Enregistrer</button>
        </form></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Double authentification</div><div class="card-body">
        @if($user->hasTwoFactor())
            <p class="mb-1"><span class="badge-st st-ok">Active</span> depuis le {{ $user->two_factor_confirmed_at->format('d/m/Y') }}.</p>
            <p class="small text-muted-2 mb-0">Codes de récupération restants : {{ count($user->two_factor_recovery_codes ?? []) }}. En cas de perte du téléphone, contactez l’administrateur technique.</p>
        @else
            <p>{{ $user->requiresTwoFactor() ? 'Obligatoire pour votre rôle.' : 'Recommandée.' }} Elle fonctionne sans Internet (application d’authentification du téléphone).</p>
            <a href="{{ route('two-factor.setup') }}" class="btn btn-accent">Configurer</a>
        @endif
        <hr><p class="small mb-0">Dernière connexion : {{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }} depuis {{ $user->last_login_ip ?? '—' }}</p>
    </div></div></div>
</div>
@endsection
