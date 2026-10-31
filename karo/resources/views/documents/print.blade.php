<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ status_label('document_type', $doc->type) }} {{ $doc->number }}</title>
<style>
    body { font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif; color: #111; font-size: 11.5px; margin: 0; background: {{ $pdf ? '#fff' : '#E8ECF2' }}; }
    .sheet { background: #fff; width: 190mm; min-height: 270mm; margin: {{ $pdf ? '0' : '16px auto' }}; padding: {{ $pdf ? '0' : '14mm' }}; position: relative; }
    .head { border-bottom: 3px solid #0E1E3A; padding-bottom: 8px; margin-bottom: 12px; }
    .head td { vertical-align: top; }
    .uni { font-size: 16px; font-weight: bold; color: #0E1E3A; }
    .title { font-size: 17px; font-weight: bold; color: #0F8F82; text-align: right; }
    table { width: 100%; border-collapse: collapse; }
    .grid td { padding: 3px 4px; }
    .items th { background: #0E1E3A; color: #fff; text-align: left; padding: 5px; font-size: 10.5px; }
    .items td { border-bottom: 1px solid #DDE3EC; padding: 5px; }
    .r { text-align: right; white-space: nowrap; }
    .prov { position: absolute; top: 40%; left: 8%; transform: rotate(-25deg); font-size: 64px; color: rgba(180, 35, 24, .16); font-weight: bold; letter-spacing: 6px; }
    .badge { display: inline-block; border: 2px solid; padding: 2px 10px; font-weight: bold; letter-spacing: 2px; }
    .foot { margin-top: 26px; font-size: 10px; color: #444; }
    .sign td { padding-top: 34px; text-align: center; }
    .toolbar { text-align: center; padding: 10px; font-family: Arial, sans-serif; }
    .toolbar a, .toolbar button { margin: 3px; padding: 7px 14px; border: 1px solid #0E1E3A; background: #fff; color: #0E1E3A; border-radius: 7px; text-decoration: none; font-size: 13px; cursor: pointer; }
    @media print { .toolbar { display: none; } body { background: #fff; } .sheet { margin: 0; padding: 0; width: auto; } }
</style>
</head>
<body>
@unless($pdf)<div class="toolbar"><button onclick="window.print()">Imprimer</button><a href="{{ route('documents.pdf', $doc) }}">Télécharger le PDF</a></div>@endunless
<div class="sheet">
    @if($doc->status !== 'final')<div class="prov">{{ $doc->status === 'provisional' ? 'PROVISOIRE' : 'ANNULÉ' }}</div>@endif
    <table class="head"><tr>
        <td><div class="uni">{{ $shop['university_name'] }}</div><div>{{ $shop['university_address'] }}</div><div>{{ trim($shop['university_phone'].' · '.$shop['university_email'], ' ·') }}</div></td>
        <td class="r"><div class="title">{{ mb_strtoupper(status_label('document_type', $doc->type)) }}</div><div>N° <strong>{{ $doc->number }}</strong></div><div>Émis le {{ $doc->issued_at->format('d/m/Y à H:i') }}</div><div>Par {{ $doc->issuer->name }}</div>
            <div style="margin-top:4px"><span class="badge" style="color:{{ $doc->status === 'final' ? '#0F7B5F' : '#B42318' }}">{{ mb_strtoupper(status_label('document', $doc->status)) }}</span></div></td>
    </tr></table>
    @php $c = $doc->content; $st = $c['student'] ?? []; @endphp
    <table class="grid"><tr><td><strong>Étudiant :</strong> {{ $st['name'] ?? '' }}</td><td><strong>Matricule :</strong> {{ $st['matricule'] ?? '' }}</td></tr>
        <tr><td><strong>Faculté :</strong> {{ $st['faculty'] ?? '—' }}</td><td><strong>Promotion :</strong> {{ $st['promotion'] ?? '—' }}</td></tr></table>
    <br>
    @if($doc->type === 'receipt')
        @php $p = $c['payment']; @endphp
        <table class="grid"><tr><td><strong>Paiement :</strong> {{ $p['number'] }}</td><td><strong>Année :</strong> {{ $p['year'] }}</td></tr>
            <tr><td><strong>Compte :</strong> {{ $p['account'] }}</td><td><strong>Référence bancaire :</strong> {{ $p['bank_reference'] }}</td></tr>
            <tr><td><strong>Date du versement :</strong> {{ $p['deposit_date'] }}</td><td><strong>Montant reçu :</strong> {{ money($p['amount'], $p['currency']) }}</td></tr></table><br>
        <table class="items"><thead><tr><th>Affectation</th><th class="r">Montant</th></tr></thead><tbody>
            @forelse($c['allocations'] as $a)<tr><td>{{ $a['label'] }}</td><td class="r">{{ money($a['amount'], $p['currency']) }}</td></tr>@empty<tr><td colspan="2">Aucune affectation (montant conservé en crédit).</td></tr>@endforelse
            @if($c['available'] !== null && \App\Support\Money::isPositive($c['available']))<tr><td>Montant non affecté (crédit disponible)</td><td class="r">{{ money($c['available'], $p['currency']) }}</td></tr>@endif
        </tbody></table>
        <p>{{ $doc->status === 'final' ? 'Paiement validé par le service de comptabilité.' : 'Accusé de dépôt : ce bordereau est en cours de contrôle. Ce document ne vaut pas reçu.' }}
           Confirmation bancaire au moment de l’émission : {{ status_label('bank', $p['bank_status']) }}.</p>
    @elseif(in_array($doc->type, ['statement', 'situation', 'attestation']))
        <p><strong>Année académique :</strong> {{ $c['year'] }}</p>
        @if($doc->type === 'attestation')
            <p>Le service de comptabilité atteste que l’étudiant(e) ci-dessus a effectué, au titre de l’année {{ $c['year'] }}, les règlements validés suivants :</p>
            <table class="items"><thead><tr><th>Devise</th><th class="r">Réglé et affecté</th><th class="r">Solde restant dû</th></tr></thead><tbody>
                @foreach($c['totals'] as $cur => $t)<tr><td>{{ $cur }}</td><td class="r">{{ money($t['settled'], $cur) }}</td><td class="r">{{ money($t['balance'], $cur) }}</td></tr>@endforeach</tbody></table>
            <p class="foot">La présente attestation ne vaut pas attestation d’admissibilité académique.</p>
        @else
            <table class="items"><thead><tr><th>Frais</th><th class="r">Net dû</th><th class="r">Réglé</th><th class="r">Solde</th></tr></thead><tbody>
                @foreach($c['lines'] as $l)<tr><td>{{ $l['label'] }}</td><td class="r">{{ money($l['net'], $l['currency']) }}</td><td class="r">{{ money($l['settled'], $l['currency']) }}</td><td class="r">{{ money($l['balance'], $l['currency']) }}</td></tr>@endforeach</tbody></table><br>
            <table class="items"><thead><tr><th>Devise</th><th class="r">Net dû</th><th class="r">Réglé</th><th class="r">Solde dû</th><th class="r">Exigible</th><th class="r">Crédit disponible</th></tr></thead><tbody>
                @foreach($c['totals'] as $cur => $t)<tr><td>{{ $cur }}</td><td class="r">{{ money($t['net'], $cur) }}</td><td class="r">{{ money($t['settled'], $cur) }}</td><td class="r">{{ money($t['balance'], $cur) }}</td><td class="r">{{ money($t['exigible'], $cur) }}</td><td class="r">{{ money($t['credit'], $cur) }}</td></tr>@endforeach</tbody></table>
            @if($doc->type === 'statement' && $c['payments'])<br><table class="items"><thead><tr><th>Paiement validé</th><th>Date</th><th class="r">Montant</th><th>Banque</th></tr></thead><tbody>
                @foreach($c['payments'] as $p)<tr><td>{{ $p['number'] }}</td><td>{{ $p['date'] }}</td><td class="r">{{ money($p['amount'], $p['currency']) }}</td><td>{{ status_label('bank', $p['bank']) }}</td></tr>@endforeach</tbody></table>@endif
            @if($c['pending_note'])<p><em>{{ $c['pending_note'] }}</em></p>@endif
        @endif
    @else
        <table class="grid">@foreach($doc->summary as $k => $v)@if(!is_array($v))<tr><td><strong>{{ ['operation' => 'Opération', 'type' => 'Nature', 'amount' => 'Montant', 'currency' => 'Devise', 'source' => 'Paiement source', 'student_initials' => 'Étudiant'][$k] ?? $k }}</strong></td><td>{{ $k === 'type' ? status_label('credit_type', $v) : $v }}</td></tr>@endif @endforeach</table>
    @endif
    <table class="sign"><tr><td>{{ $shop['signatory_2_title'] }}<br>{{ $shop['signatory_2_name'] }}</td><td>{{ $shop['signatory_1_title'] }}<br>{{ $shop['signatory_1_name'] }}</td></tr></table>
    <table class="foot"><tr><td>Document généré par Karo. Empreinte : {{ substr($doc->content_hash, 0, 16) }}…<br>Vérification : {{ $url }}<br>La vérification n’affiche que les informations minimales (type, numéro, date, statut, initiales).</td>
        <td class="r">@if($qr)<img src="{{ $qr }}" width="110" height="110" alt="QR code de vérification">@endif</td></tr></table>
</div>
</body>
</html>
