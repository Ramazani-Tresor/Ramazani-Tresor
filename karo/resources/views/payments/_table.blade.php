<div class="table-responsive"><table class="table table-hover table-stack">
    <thead><tr><th>N°</th><th>Étudiant</th><th>Date bancaire</th><th>Compte / référence</th><th class="num">Montant</th><th>Statut</th><th>Banque</th><th>Saisi par</th></tr></thead>
    <tbody>
    @forelse($items as $p)
        <tr>
            <td data-l="N°"><a href="{{ route('payments.show', $p) }}" class="fw-600">{{ $p->number }}</a>@if($p->anomalies)<i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="{{ collect($p->anomalies)->pluck('message')->join(' ; ') }}"></i><span class="visually-hidden">Anomalie</span>@endif @if($p->channel === 'offline_sync')<i class="bi bi-cloud-arrow-up text-muted-2 ms-1" title="Saisi hors connexion"></i>@endif</td>
            <td data-l="Étudiant">{{ $p->student->matricule }}<div class="small">{{ $p->student->fullName() }}</div></td>
            <td data-l="Date">{{ $p->deposit_date?->format('d/m/Y') }}</td>
            <td data-l="Compte" class="small">{{ $p->bankAccount?->bank->code }} {{ $p->bankAccount?->account_number }}<br>{{ $p->bank_reference }}</td>
            <td data-l="Montant" class="num">{{ money($p->amount, $p->currency) }}</td>
            <td data-l="Statut">{!! \App\Support\Labels::badge('payment', $p->status) !!}</td>
            <td data-l="Banque">{!! \App\Support\Labels::badge('bank', $p->bank_status) !!}</td>
            <td data-l="Saisi par" class="small">{{ $p->creator->name }}<br>{{ $p->created_at->format('d/m H:i') }}</td>
        </tr>
    @empty
        <tr><td colspan="8">@include('partials.empty', ['icon' => 'bi-receipt', 'text' => $emptyText ?? 'Aucun paiement.'])</td></tr>
    @endforelse
    </tbody>
</table></div>
