@extends('layouts.app')
@section('title', 'Synchronisation')
@section('content')
@include('partials.head', ['title' => 'Synchronisation et saisie hors connexion', 'sub' => 'Le serveur de référence est la seule autorité d’écriture. Une saisie préparée hors connexion reste provisoire jusqu’à son acceptation ; elle devient alors un bordereau « soumis au contrôle », jamais validé automatiquement.'])
<div class="row g-3">
    @if(can_do('sync.use'))
    <div class="col-xl-5">
        <div class="card mb-3"><div class="card-header">Ce poste <button class="btn btn-sm btn-primary" id="syncNow" type="button"><i class="bi bi-arrow-repeat"></i> Synchroniser maintenant</button></div>
            <div class="card-body small">
                <div>Serveur : <strong id="srvState">vérification…</strong> · Identifiant du poste : <code id="devId"></code></div>
                <div>Dernière synchronisation : <strong id="lastSync">—</strong></div>
                <div>Données de consultation locales : <strong id="snapState">aucune</strong> <button class="btn btn-link btn-sm p-0" id="snapRefresh" type="button">actualiser</button></div>
                <p class="text-muted-2 mt-2 mb-0">Ces données (matricule, nom, promotion) expirent automatiquement après {{ setting('offline_ttl_hours') }} h et sont effacées à la déconnexion. Les saisies non acquittées restent dans la file de ce poste.</p>
            </div>
            <ul class="list-group list-group-flush" id="queueList"><li class="list-group-item small text-muted-2">File vide.</li></ul>
        </div>
        <form class="card" id="offlineForm" novalidate>
            <div class="card-header">Préparer un bordereau (provisoire)</div>
            <div class="card-body row g-2">
                <div class="col-12"><label class="form-label" for="ofMat">Étudiant (matricule)</label><input id="ofMat" class="form-control" list="ofStudents" required autocomplete="off"><datalist id="ofStudents"></datalist></div>
                <div class="col-md-6"><label class="form-label" for="ofYear">Année</label><select id="ofYear" class="form-select">@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="ofAcc">Compte</label><select id="ofAcc" class="form-select">@foreach($accounts as $a)<option value="{{ $a->id }}" data-cur="{{ $a->currency }}">{{ $a->display() }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="ofRef">Référence bancaire</label><input id="ofRef" class="form-control" required maxlength="80"></div>
                <div class="col-md-6"><label class="form-label" for="ofDate">Date du versement</label><input id="ofDate" type="date" class="form-control" required max="{{ now()->toDateString() }}"></div>
                <div class="col-md-6"><label class="form-label" for="ofAmt">Montant</label><input id="ofAmt" class="form-control text-end" inputmode="decimal" required></div>
                <div class="col-md-6"><label class="form-label" for="ofProof">Justificatif ({{ (int) (setting('offline_max_payload_kb') / 1024) }} Mo max.)</label><input id="ofProof" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                <div class="col-12 small text-muted-2">La répartition entre les frais sera faite par le contrôleur sur le serveur (barèmes et soldes à jour).</div>
                <div class="col-12"><div class="alert alert-danger d-none small" id="ofErr"></div></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-accent" type="submit"><i class="bi bi-cloud-plus"></i> Ajouter à la file</button></div>
        </form>
    </div>
    @endif
    <div class="col-xl-{{ can_do('sync.use') ? 7 : 12 }}">
        <div class="card mb-3"><div class="card-header">Opérations reçues par le serveur
            <div class="btn-group" role="group" aria-label="Filtre">@foreach(['' => 'Toutes', 'conflict' => 'En conflit', 'accepted' => 'Acceptées', 'rejected' => 'Rejetées'] as $k => $l)<a class="btn btn-sm {{ request('status', '') === $k ? 'btn-primary' : 'btn-light' }}" href="{{ route('sync.index', array_filter(['status' => $k])) }}">{{ $l }}</a>@endforeach</div></div>
            <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Préparée</th><th>Poste / auteur</th><th>Contenu</th><th>Statut</th><th>Messages</th></tr></thead><tbody>
            @forelse($ops as $o)
                <tr><td data-l="Préparée" class="small">{{ $o->prepared_at?->format('d/m/Y H:i') ?? '—' }}<br>reçue {{ $o->created_at->format('d/m H:i') }} ({{ $o->attempts }}×)</td><td data-l="Poste" class="small">{{ \Illuminate\Support\Str::limit($o->device_id, 18) }}<br>{{ $o->user->name }}</td>
                    <td data-l="Contenu" class="small">{{ $o->payload['matricule'] ?? '' }} · {{ $o->payload['bank_reference'] ?? '' }} · {{ money($o->payload['amount'] ?? 0, $o->payload['currency'] ?? '') }}</td>
                    <td data-l="Statut">{!! \App\Support\Labels::badge('sync', $o->status) !!}@if($o->result)<br><a class="small" href="{{ route('payments.show', $o->result) }}">{{ $o->result->number }}</a>@endif</td>
                    <td data-l="Messages" class="small">{{ implode(' ', $o->messages ?? []) }}{{ $o->resolution_note ? ' — Décision : '.$o->resolution_note : '' }}
                        @if($o->status === 'conflict' && !$o->resolved_at && can_do('sync.resolve'))<form method="post" action="{{ route('sync.resolve', $o) }}" class="d-flex flex-wrap gap-1 mt-1">@csrf<input name="note" class="form-control form-control-sm" placeholder="Commentaire *" required aria-label="Commentaire"><button class="btn btn-sm btn-accent" name="decision" value="accept">Accepter</button><button class="btn btn-sm btn-outline-danger" name="decision" value="reject">Rejeter</button></form>@endif</td></tr>
            @empty<tr><td colspan="5">@include('partials.empty', ['icon' => 'bi-cloud-check', 'text' => 'Aucune opération.'])</td></tr>@endforelse
            </tbody></table></div><div class="card-body">{{ $ops->links() }}</div></div>
        @if($devices->isNotEmpty())
        <div class="card"><div class="card-header">Postes</div><div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Poste</th><th>Utilisateur</th><th>Dernière activité</th><th>Dernière synchronisation</th></tr></thead><tbody>
            @foreach($devices as $d)<tr><td data-l="Poste" class="small">{{ $d->device_id }}</td><td data-l="Utilisateur">{{ $d->user?->name }}</td><td data-l="Activité">{{ $d->last_seen_at?->diffForHumans() }}</td><td data-l="Synchro">{{ $d->last_sync_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>@endforeach
        </tbody></table></div></div>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    if (!document.getElementById('queueList') || !window.KaroOffline) return;
    const K = window.KaroOffline, $ = (id) => document.getElementById(id);
    const labels = { pending: ['En attente', 'warn'], accepted: ['Acceptée', 'ok'], rejected: ['Rejetée', 'danger'], conflict: ['En conflit', 'danger'] };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    async function render() {
        $('devId').textContent = K.deviceId();
        $('srvState').textContent = K.isOnline() ? 'joignable' : 'injoignable';
        const ls = await K.getMeta('last_sync'); $('lastSync').textContent = ls ? new Date(ls).toLocaleString('fr-FR') : 'jamais';
        const snap = await K.snapshot();
        $('snapState').textContent = snap ? `${snap.students.length} étudiant(s), générées le ${new Date(snap.generated_at).toLocaleString('fr-FR')}, expirent le ${new Date(snap.expires_at).toLocaleString('fr-FR')}` : 'aucune (ou expirées)';
        $('ofStudents').innerHTML = snap ? snap.students.map(s => `<option value="${esc(s.matricule)}">${esc(s.name)} — ${esc(s.promotion || '')}</option>`).join('') : '';
        const q = (await K.list()).sort((a, b) => a.created_at < b.created_at ? 1 : -1);
        $('queueList').innerHTML = q.length ? q.map(e => { const [l, c] = labels[e.status] || [e.status, 'muted'];
            return `<li class="list-group-item small"><div class="d-flex justify-content-between"><strong>${esc(e.display.matricule)} · ${esc(e.display.reference)}</strong><span class="badge-st st-${c}">${l}</span></div>${esc(e.display.amount)} — préparée le ${new Date(e.created_at).toLocaleString('fr-FR')}${e.payment_number ? ' — <strong>' + esc(e.payment_number) + '</strong>' : ''}${(e.messages || []).map(m => `<div class="text-muted-2">${esc(m)}</div>`).join('')}<div class="text-muted-2">PROVISOIRE tant que non acceptée</div></li>`; }).join('') : '<li class="list-group-item small text-muted-2">File vide.</li>';
    }
    $('syncNow').onclick = async () => { await K.ping(); await K.sync(); render(); };
    $('snapRefresh').onclick = async () => { await K.ping(); await K.refreshSnapshot(); render(); };
    document.addEventListener('karo:status', render);
    $('offlineForm').addEventListener('submit', async (e) => {
        e.preventDefault(); e.stopImmediatePropagation();
        const err = $('ofErr'); err.classList.add('d-none');
        const amount = $('ofAmt').value.replace(/\s/g, '').replace(',', '.');
        if (!/^\d+(\.\d{1,2})?$/.test(amount) || !$('ofMat').value || !$('ofRef').value || !$('ofDate').value) { err.textContent = 'Complétez tous les champs (montant numérique, 2 décimales maximum).'; err.classList.remove('d-none'); return; }
        let proof = null; const f = $('ofProof').files[0];
        if (f) { if (f.size > {{ (int) setting('offline_max_payload_kb') }} * 1024) { err.textContent = 'Justificatif trop volumineux.'; err.classList.remove('d-none'); return; }
            proof = await new Promise(r => { const fr = new FileReader(); fr.onload = () => r(String(fr.result).split(',')[1]); fr.readAsDataURL(f); }); }
        const acc = $('ofAcc').selectedOptions[0];
        const payload = { client_uuid: K.uuid(), matricule: $('ofMat').value.trim(), academic_year_id: Number($('ofYear').value), bank_account_id: Number(acc.value), currency: acc.dataset.cur,
            bank_reference: $('ofRef').value.trim(), deposit_date: $('ofDate').value, amount, proof_base64: proof, prepared_at: new Date().toISOString() };
        await K.enqueue(payload, { matricule: payload.matricule, reference: payload.bank_reference, amount: amount + ' ' + acc.dataset.cur });
        e.target.reset(); render();
    }, true);
    render();
})();
</script>
@endpush
