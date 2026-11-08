@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
@include('partials.head', ['title' => 'Mes notifications', 'sub' => 'Historique des notifications internes (les mêmes messages sont envoyés par courriel lorsqu’une adresse est connue).', 'actions' => '<form method="post" action="'.route('notifications.read').'">'.csrf_field().'<button class="btn btn-light"><i class="bi bi-check2-all"></i> Tout marquer comme lu</button></form>'])
<div class="card">
@forelse($notifications as $n)
    <div class="p-3 border-bottom {{ $n->read_at ? '' : 'bg-sky' }}">
        <div class="d-flex justify-content-between gap-2 flex-wrap"><span class="fw-600 text-navy">{{ $n->data['title'] ?? 'Notification' }}</span><span class="small text-muted-2">{{ $n->created_at->format('d/m/Y H:i') }}</span></div>
        <div>{{ $n->data['body'] ?? '' }}</div>
        @if(! empty($n->data['url']))<a class="small" href="{{ $n->data['url'] }}">Ouvrir</a>@endif
    </div>
@empty
    @include('partials.empty', ['icon' => 'bi-bell-slash', 'text' => 'Aucune notification.'])
@endforelse
<div class="card-body">{{ $notifications->links() }}</div>
</div>
@endsection
