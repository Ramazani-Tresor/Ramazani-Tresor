@extends('layouts.app')
@section('title', 'Ma caisse')
@section('content')
<div class="page-head"><div><h1>Ouvrir ma caisse</h1><div class="sub">Une session de caisse est requise pour encaisser.</div></div></div>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><form class="card-body" method="post" action="{{ route('cash.open') }}">@csrf
        <label class="form-label fw-600">Fonds de caisse initial (espèces comptées)</label>
        <div class="input-group input-group-lg mb-3"><input name="opening_amount" class="form-control text-end fw-bold" inputmode="decimal" value="{{ old('opening_amount', '0') }}" required autofocus><span class="input-group-text">{{ setting('currency_symbol') }}</span></div>
        <button class="btn btn-primary btn-lg w-100"><i class="bi bi-unlock me-1"></i>Ouvrir la caisse</button>
    </form></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header">Mes dernières sessions</div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Session</th><th>Ouverture</th><th>Clôture</th><th class="num">Écart</th></tr></thead><tbody>
        @forelse($lastSessions as $s)<tr><td><a href="{{ route('cash.show', $s) }}">{{ $s->number }}</a></td><td>{{ $s->opened_at->format('d/m H:i') }}</td><td>{{ $s->closed_at?->format('d/m H:i') ?? '—' }}</td><td class="num {{ (float) $s->difference < 0 ? 'text-danger' : '' }}">{{ $s->difference !== null ? money($s->difference) : '—' }}</td></tr>
        @empty<tr><td colspan="4" class="empty">Aucune session.</td></tr>@endforelse
        </tbody></table></div></div></div>
</div>
@endsection
