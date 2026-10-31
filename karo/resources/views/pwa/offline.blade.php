<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Serveur injoignable</title>
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body class="d-grid" style="place-items:center;min-height:100vh"><main class="text-center p-4" style="max-width:520px"><img src="{{ asset('images/logo.svg') }}" width="56" alt="">
<h1 class="h4 mt-3 text-navy">Serveur de référence injoignable</h1>
<p class="text-muted-2">Cette page nécessite le serveur. Si la page <strong>Synchronisation</strong> a été ouverte sur ce poste, vous pouvez y consulter les données limitées et préparer des bordereaux provisoires, envoyés automatiquement au retour du réseau.</p>
<a href="{{ url('/synchronisation') }}" class="btn btn-primary">Ouvrir la synchronisation</a></main></body></html>
