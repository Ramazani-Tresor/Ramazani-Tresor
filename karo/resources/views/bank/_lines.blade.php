<div class="table-responsive"><table class="table table-sm table-hover table-stack mb-0">
    <thead><tr><th>#</th><th>Compte</th><th>Opération / valeur</th><th>Référence</th><th>Libellé</th><th class="num">Montant</th><th class="num">Rapproché</th><th>Fiabilité</th><th>État</th></tr></thead>
    <tbody>@forelse($lines as $l)
        <tr><td data-l="#"><a href="{{ route('bank.lines.show', $l) }}">#{{ $l->id }}</a></td><td data-l="Compte" class="small">{{ $l->bankAccount->bank->code }} {{ $l->bankAccount->account_number }}</td>
            <td data-l="Dates" class="small">{{ $l->operation_date->format('d/m/Y') }}<br>{{ $l->value_date?->format('d/m/Y') }}</td><td data-l="Réf.">{{ $l->reference }}</td><td data-l="Libellé" class="small">{{ \Illuminate\Support\Str::limit($l->description, 40) }}</td>
            <td data-l="Montant" class="num">{{ money($l->amount, $l->currency) }}</td><td data-l="Rapproché" class="num">{{ money($l->matched_amount, $l->currency) }}</td>
            <td data-l="Fiabilité">{!! \App\Support\Labels::badge('line_review', $l->review_status) !!}</td><td data-l="État">{!! \App\Support\Labels::badge('line_match', $l->match_status) !!}</td></tr>
    @empty<tr><td colspan="9">@include('partials.empty', ['text' => 'Aucune ligne.'])</td></tr>@endforelse</tbody>
</table></div>
