@extends('layouts.app')
@section('title', 'Synchronisation')
@section('content')
<div class="page-head"><div><h1>Synchronisation des ventes hors ligne</h1><div class="sub">Les ventes en conflit ne sont jamais modifiées automatiquement : décidez de les accepter (aux prix du ticket) ou de les rejeter.</div></div></div>
<ul class="nav nav-pills mb-3">
    @foreach(\App\Models\SyncOperation::STATUSES as $k => $v)<li class="nav-item"><a class="nav-link {{ $status === $k ? 'active' : '' }}" href="{{ route('sync.index', ['status' => $k]) }}">{{ $v }}</a></li>@endforeach
</ul>
@forelse($operations as $op)
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <span><i class="bi bi-phone me-1"></i>{{ $op->user->name }} · appareil {{ \Illuminate\Support\Str::limit($op->device_id, 16) }} · saisie le {{ $op->created_offline_at?->format('d/m/Y H:i') ?? '?' }} · reçue le {{ $op->created_at->format('d/m/Y H:i') }} ({{ $op->attempts }} tentative(s))</span>
            <span class="status {{ ['synced' => 'status-ok', 'conflict' => 'status-danger', 'rejected' => 'status-muted'][$op->status] }}">{{ \App\Models\SyncOperation::STATUSES[$op->status] }}</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-6">
                    <table class="table table-sm mb-2"><thead><tr><th>Article (id)</th><th class="num">Qté</th><th class="num">Prix ticket</th></tr></thead><tbody>
                        @foreach($op->payload['items'] ?? [] as $it)
                            @php $prod = \App\Models\Product::find($it['product_id']); @endphp
                            <tr><td>{{ $prod?->name ?? '#'.$it['product_id'] }}</td><td class="num">{{ qty($it['quantity']) }}</td><td class="num">{{ isset($it['unit_price']) ? money($it['unit_price']) : '—' }}</td></tr>
                        @endforeach
                    </tbody></table>
                    <div class="small">Paiements : @foreach($op->payload['payments'] ?? [] as $p){{ \App\Models\PaymentMethod::find($p['payment_method_id'])?->name }} {{ money($p['amount'] ?? 0) }}@if(!$loop->last), @endif @endforeach</div>
                </div>
                <div class="col-lg-6">
                    @foreach($op->conflicts ?? [] as $c)<div class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>{{ $c['message'] }}</div>@endforeach
                    @if($op->resolver)<div class="small mt-2">Traitée par {{ $op->resolver->name }} le {{ $op->resolved_at->format('d/m/Y H:i') }}{{ $op->resolution_note ? ' — '.$op->resolution_note : '' }}</div>@endif
                    @if($op->result)<div class="small mt-1">Vente créée : <a href="{{ route('sales.show', $op->result) }}">{{ $op->result->number }}</a></div>@endif
                    @if($op->status === 'conflict')
                        <form method="post" action="{{ route('sync.resolve', $op) }}" class="mt-3">@csrf
                            <input name="note" class="form-control form-control-sm mb-2" placeholder="Commentaire (obligatoire en cas de rejet)" maxlength="255">
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-primary" name="decision" value="accept" data-confirm="Accepter la vente aux prix du ticket ? Le stock sera décrémenté (il peut devenir insuffisant : vérifiez avant)."><i class="bi bi-check2 me-1"></i>Accepter</button>
                                <button class="btn btn-sm btn-outline-danger" name="decision" value="reject" data-confirm="Rejeter cette vente ? Pensez à régulariser l'argent perçu (remboursement du client ou apport en caisse)."><i class="bi bi-x-lg me-1"></i>Rejeter</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="card"><div class="empty"><i class="bi bi-cloud-check"></i>Aucune opération dans cet état.</div></div>
@endforelse
{{ $operations->links() }}
@endsection
