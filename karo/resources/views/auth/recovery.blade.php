@extends('layouts.guest')
@section('title', 'Récupération du compte')
@section('content')
<h2 class="h4 fw-bold text-navy">Récupération du compte</h2>
<p>L’application fonctionne sur le réseau local, sans envoi de courriel. La récupération se fait donc ainsi :</p>
<ol class="small">
    <li><strong>Téléphone d’authentification perdu</strong> : connectez-vous avec l’un de vos <strong>codes de récupération</strong> (fournis à l’activation, à usage unique), puis reconfigurez la double authentification.</li>
    <li><strong>Mot de passe oublié</strong> ou plus aucun code : présentez-vous à l’<strong>administrateur technique</strong>. Après vérification de votre identité, il génère un <strong>mot de passe temporaire</strong> (changement obligatoire à la connexion) et peut réinitialiser votre double authentification. L’opération est inscrite au journal d’audit.</li>
</ol>
<a href="{{ route('login') }}" class="btn btn-primary w-100">Retour à la connexion</a>
@endsection
