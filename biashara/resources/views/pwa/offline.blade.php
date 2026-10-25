<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hors ligne</title>
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body class="d-grid" style="place-items:center;min-height:100vh"><div class="text-center p-4" style="max-width:460px">
<img src="{{ asset('images/logo.svg') }}" width="64" alt="">
<h1 class="h4 mt-3 text-navy">Vous êtes hors ligne</h1>
<p class="text-muted-2">Cette page n'est pas disponible sans connexion. Le <strong>point de vente</strong> reste utilisable s'il a été ouvert au moins une fois sur cet appareil : les ventes y sont enregistrées localement puis synchronisées au retour du réseau.</p>
<a href="{{ url('/caisse-vente') }}" class="btn btn-primary">Ouvrir le point de vente</a>
</div></body></html>
