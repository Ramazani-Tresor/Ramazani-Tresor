@extends('layouts.app')
@section('title', 'Jetons d’API')
@section('content')
@include('partials.head', ['title' => 'Jetons d’accès à l’API', 'sub' => 'API v1 en lecture seule (/api/v1/agents, /agents/{matricule}, /structures, /statistiques, /patrimoine). Un jeton agit avec les droits et le périmètre de son compte ; seule son empreinte est conservée.'])
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Nom</th><th>Compte</th><th>Créé</th><th>Dernier usage</th><th>Expiration</th><th>État</th><th></th></tr></thead><tbody>
@forelse($tokens as $t)<tr><td data-l="Nom">{{ $t->name }}</td><td data-l="Compte">{{ $t->user?->name }}</td><td data-l="Créé">{{ $t->created_at->format('d/m/Y') }}</td><td data-l="Usage">{{ $t->last_used_at?->format('d/m/Y H:i') ?? '—' }}</td><td data-l="Expiration">{{ $t->expires_at?->format('d/m/Y') ?? 'aucune' }}</td><td data-l="État">{!! $t->revoked_at ? '<span class="badge-st st-muted">Révoqué</span>' : '<span class="badge-st st-ok">Actif</span>' !!}</td><td>@unless($t->revoked_at)<form method="post" action="{{ route('tokens.revoke', $t) }}" data-confirm="Révoquer ce jeton ?">@csrf<button class="btn btn-sm btn-outline-danger">Révoquer</button></form>@endunless</td></tr>@empty<tr><td colspan="7" class="text-muted-2">Aucun jeton.</td></tr>@endforelse
</tbody></table></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header">Nouveau jeton</div><div class="card-body"><form method="post" action="{{ route('tokens.store') }}" class="row g-2">@csrf
    @include('partials.field', ['name' => 'name', 'label' => 'Nom (usage)', 'required' => true, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'user_id', 'label' => 'Compte titulaire', 'type' => 'select', 'required' => true, 'options' => $users->pluck('name', 'id')->all(), 'col' => 'col-12'])
    @include('partials.field', ['name' => 'expires_at', 'label' => 'Expiration', 'type' => 'date', 'col' => 'col-12'])
    <div class="col-12"><button class="btn btn-primary">Créer</button></div>
</form></div></div></div>
</div>
@endsection
