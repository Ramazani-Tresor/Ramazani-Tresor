<div class="dropdown" wire:poll.60s>
    <button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications ({{ $count }} non lues)">
        <i class="bi bi-bell"></i>
        @if($count)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $count > 99 ? '99+' : $count }}</span>@endif
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-sm p-0" style="width:340px;max-width:92vw">
        <div class="px-3 py-2 border-bottom fw-600 text-navy">Notifications</div>
        @forelse($latest as $n)
            <a class="dropdown-item py-2 {{ $n->read_at ? '' : 'bg-sky' }}" href="{{ $n->data['url'] ?? route('notifications.index') }}" wire:click="markRead('{{ $n->id }}')" style="white-space:normal">
                <div class="small fw-600">{{ $n->data['title'] ?? 'Notification' }}</div>
                <div class="mini text-muted-2">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 110) }}</div>
                <div class="mini text-muted-2">{{ $n->created_at->diffForHumans() }}</div>
            </a>
        @empty
            <div class="px-3 py-3 small text-muted-2">Aucune notification.</div>
        @endforelse
        <a class="dropdown-item text-center small border-top py-2" href="{{ route('notifications.index') }}">Voir tout l’historique</a>
    </div>
</div>
