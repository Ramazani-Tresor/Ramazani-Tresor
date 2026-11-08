@extends('layouts.app')
@section('title', 'Sauvegardes')
@section('content')
@include('partials.head', ['title' => 'Sauvegardes chiffrées', 'sub' => 'Base de données et documents numérisés, chiffrés en AES-256-GCM, avec copie vers un emplacement séparé. Planification quotidienne : php artisan schedule:run (cron).', 'actions' => $configured ? '<form method="post" action="'.route('backups.create').'" data-confirm="Lancer une sauvegarde maintenant ?">'.csrf_field().'<button class="btn btn-primary"><i class="bi bi-hdd-stack"></i> Sauvegarder maintenant</button></form>' : ''])
@unless($configured)<div class="alert alert-danger">Clé de chiffrement absente : exécutez <code>php artisan grh:backup-key</code> et conservez la clé hors du serveur.</div>@endunless
@unless($secondary)<div class="alert alert-warning">Aucun emplacement secondaire (BACKUP_SECONDARY_PATH) : configurez une copie sur un autre disque ou serveur.</div>@endunless
<div class="card"><div class="table-responsive"><table class="table table-stack"><thead><tr><th>Fichier</th><th>Date</th><th>Taille</th><th></th></tr></thead><tbody>
@forelse($files as $f)<tr><td data-l="Fichier"><code>{{ $f['name'] }}</code></td><td data-l="Date">{{ $f['date'] }}</td><td data-l="Taille">{{ number_format($f['size'] / 1024, 0, ',', ' ') }} Ko</td><td><a class="btn btn-sm btn-light" href="{{ route('backups.download', $f['name']) }}"><i class="bi bi-download"></i></a></td></tr>@empty<tr><td colspan="4" class="text-muted-2">Aucune sauvegarde.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
