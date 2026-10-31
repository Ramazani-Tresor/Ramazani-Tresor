<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>body{font-family:"DejaVu Sans",sans-serif;font-size:9.5px}h1{font-size:14px;color:#0E1E3A}table{width:100%;border-collapse:collapse}th{background:#0E1E3A;color:#fff;padding:4px;text-align:left}td{border-bottom:1px solid #ddd;padding:3px}</style></head><body>
<h1>{{ setting('university_name') }} — Contrôle financier : {{ $session->name }} ({{ $session->academicYear->code }})</h1>
<p>Conformité financière uniquement — ne vaut pas admissibilité académique. Édité le {{ now()->format('d/m/Y H:i') }}.</p>
<table><thead><tr><th>Matricule</th><th>Nom</th><th>Promotion</th><th>Réglé / requis</th><th>Statut</th></tr></thead><tbody>
@foreach($rows as $r)<tr><td>{{ $r['enrollment']->student->matricule }}</td><td>{{ $r['enrollment']->student->fullName() }}</td><td>{{ $r['enrollment']->promotion->code }}</td><td>@foreach($r['details'] as $cur => $d){{ money($d['settled'], $cur) }} / {{ money($d['required'], $cur) }} @endforeach</td><td>{{ status_label('clearance', $r['status']) }}{{ $r['waiver'] ? ' ('.$r['waiver']->authority.')' : '' }}</td></tr>@endforeach
</tbody></table></body></html>
