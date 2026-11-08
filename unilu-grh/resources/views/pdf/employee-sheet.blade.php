<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Fiche {{ $e->matricule }}</title>@include('pdf._styles')</head><body>
@include('pdf._letterhead', ['reference' => $reference])
<div class="foot">Fiche individuelle {{ $e->matricule }} · document confidentiel</div>
<h1>Fiche individuelle du personnel</h1><div class="sub">{{ $e->fullName() }} · matricule {{ $e->matricule }}</div>
@php $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; $L = fn ($g, $v) => \App\Support\Labels::status($g, $v); @endphp
<table style="width:100%"><tr><td style="vertical-align:top">
<h2>Identité</h2><table class="kv">
    <tr><td class="k">Nom, postnom, prénom</td><td>{{ $e->fullName() }}</td></tr><tr><td class="k">Sexe</td><td>{{ $L('sex', $e->sex) }}</td></tr>
    <tr><td class="k">Naissance</td><td>{{ $d($e->birth_date) }} {{ $e->birth_place ? 'à '.$e->birth_place : '' }}</td></tr><tr><td class="k">Nationalité</td><td>{{ $e->nationality }}</td></tr>
    <tr><td class="k">État civil</td><td>{{ $L('marital', $e->marital_status) }} · {{ $e->children_count }} enfant(s)</td></tr><tr><td class="k">Adresse / contacts</td><td>{{ $e->address }} {{ $e->phone }} {{ $e->email }}</td></tr>
    <tr><td class="k">Personne à contacter</td><td>{{ $e->emergency_contact_name }} {{ $e->emergency_contact_phone }}</td></tr>
</table></td>
@if($e->photo_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($e->photo_path))<td style="width:100px;text-align:right;vertical-align:top"><img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($e->photo_path) }}" width="90" alt=""></td>@endif
</tr></table>
<h2>Situation administrative</h2><table class="kv">
    <tr><td class="k">Famille / catégorie</td><td>{{ $L('family', $e->staff_family) }} · {{ $e->category?->name }}</td></tr><tr><td class="k">Grade / mandat</td><td>{{ $e->grade?->name }} {{ $e->mandate ? '· '.$e->mandate->name.' (fin '.$d($e->mandate_end).')' : '' }}</td></tr>
    @if($e->adminGrade)<tr><td class="k">Catégorie administrative</td><td>{{ $e->adminGrade->name }}</td></tr>@endif
    <tr><td class="k">Fonction</td><td>{{ $e->jobFunction?->name }}</td></tr><tr><td class="k">Affectation</td><td>{{ $e->activeAssignment?->orgUnit?->pathName() ?? '—' }} {{ $e->activeAssignment?->position ? '· '.$e->activeAssignment->position->title : '' }}</td></tr>
    <tr><td class="k">Première prise de service / engagement</td><td>{{ $d($e->first_service_date) }} / {{ $d($e->engagement_date) }}</td></tr><tr><td class="k">Situation</td><td>{{ $e->statusLabel() }} depuis le {{ $d($e->status_since) }}</td></tr>
</table>
<h2>Carrière</h2><table class="data"><thead><tr><th>Effet</th><th>Évolution</th><th>Décision</th><th>Autorité</th></tr></thead><tbody>@forelse($career as $ev)<tr><td>{{ $d($ev->effective_date) }}</td><td>{{ $L('career_type', $ev->type) }} {{ $ev->newGrade?->name ?? $ev->newMandate?->name ?? $ev->newCategory?->name }}</td><td>{{ $ev->decision_number }} du {{ $d($ev->decision_date) }}</td><td>{{ $ev->signing_authority }}</td></tr>@empty<tr><td colspan="4">—</td></tr>@endforelse</tbody></table>
<h2>Diplômes et travaux scientifiques</h2><table class="data"><thead><tr><th>Nature</th><th>Intitulé</th><th>Université</th><th>Date</th><th>Mention</th></tr></thead><tbody>@forelse($qualifications as $q)<tr><td>{{ $L('qualification', $q->nature) }}</td><td>{{ $q->title }}</td><td>{{ $q->defense_university ?: $q->institution }}</td><td>{{ $d($q->defense_date) }}</td><td>{{ $q->mention }}</td></tr>@empty<tr><td colspan="5">—</td></tr>@endforelse</tbody></table>
<h2>Affectations</h2><table class="data"><thead><tr><th>Période</th><th>Structure</th><th>Poste</th><th>Motif</th></tr></thead><tbody>@foreach($assignments as $a)<tr><td>{{ $d($a->start_date) }} → {{ $a->end_date ? $d($a->end_date) : '…' }}</td><td>{{ $a->orgUnit?->name }}</td><td>{{ $a->position?->title }}</td><td>{{ $a->reason }}</td></tr>@endforeach</tbody></table>
@if($summary)<h2>Primes (par devise)</h2><table class="data"><thead><tr><th>Devise</th><th>Prévu</th><th>Payé</th><th>Acomptes</th><th>Solde</th><th>Arriérés</th></tr></thead><tbody>@foreach($summary as $c => $t)<tr><td>{{ $c }}</td><td>{{ money($t['net']) }}</td><td>{{ money($t['paid']) }}</td><td>{{ money($t['advances']) }}</td><td>{{ money($t['balance']) }}</td><td>{{ money($t['arrears']) }}</td></tr>@endforeach</tbody></table>@endif
@include('pdf._signatures')
</body></html>
