@extends('layouts.app')
@section('title', 'Relevé '.$batch->number)
@section('content')
@include('partials.head', ['title' => 'Relevé '.$batch->number, 'crumbs' => ['Banque' => route('bank.index')], 'sub' => e($batch->original_name).' · '.e($batch->bankAccount->display()).' · <a href="'.route('bank.batches.source', $batch).'">fichier source</a>'])
@if(in_array($batch->status, ['uploaded', 'previewed']) && can_do('bank.import'))
<form method="post" action="{{ route('bank.preview', $batch) }}" class="card mb-3">@csrf
    <div class="card-header">Colonnes et période couverte</div>
    <div class="card-body row g-2">
        @foreach($fields as $f => [$label, $req])
            <div class="col-md-3"><label class="form-label" for="m-{{ $f }}">{{ $label }} {{ $req ? '*' : '' }}</label><select id="m-{{ $f }}" name="map[{{ $f }}]" class="form-select form-select-sm" @required($req)><option value="">—</option>@foreach($headers as $i => $h)<option value="{{ $i }}" @selected(isset($batch->column_map[$f]) && (int) $batch->column_map[$f] === $i)>{{ $h !== '' ? $h : 'Colonne '.($i + 1) }}</option>@endforeach</select></div>
        @endforeach
        <div class="col-md-3"><label class="form-label" for="ps">Début de période</label><input id="ps" type="date" name="period_start" class="form-control form-control-sm" value="{{ $batch->period_start?->toDateString() }}"></div>
        <div class="col-md-3"><label class="form-label" for="pe">Fin de période</label><input id="pe" type="date" name="period_end" class="form-control form-control-sm" value="{{ $batch->period_end?->toDateString() }}"></div>
        <div class="col-12 small text-muted-2">Vide : période déduite des dates d’opération. La période sert à mesurer la couverture des relevés dans le rapport.</div>
    </div>
    <div class="card-footer bg-white"><button class="btn btn-primary">Prévisualiser</button></div>
</form>
@endif
@if($analysis)
    @php $s = $analysis['summary']; @endphp
    <div class="row g-2 mb-3">
        <div class="col-6 col-md"><div class="card kpi"><div class="l">Crédits lus</div><div class="v">{{ $s['credits'] }}</div></div></div>
        <div class="col-6 col-md"><div class="card kpi warn-k"><div class="l">Déjà connues (chevauchement)</div><div class="v">{{ $s['duplicates'] }}</div></div></div>
        <div class="col-6 col-md"><div class="card kpi alert-k"><div class="l">Incertaines → vérification</div><div class="v">{{ $s['uncertain'] }}</div></div></div>
        <div class="col-6 col-md"><div class="card kpi alert-k"><div class="l">En erreur</div><div class="v">{{ $s['errors'] }}</div></div></div>
        <div class="col-6 col-md"><div class="card kpi"><div class="l">Débits ignorés</div><div class="v">{{ $s['debits_ignored'] }}</div></div></div>
        <div class="col-6 col-md"><div class="card kpi accent"><div class="l">Total nouveau</div><div class="v" style="font-size:1rem">{{ money($s['total'], $batch->bankAccount->currency) }}</div></div></div>
    </div>
    <div class="card mb-3"><div class="table-responsive" style="max-height:460px"><table class="table table-sm table-stack mb-0">
        <thead><tr><th>Ligne</th><th>Opération</th><th>Valeur</th><th>Référence</th><th>Libellé</th><th class="num">Montant</th><th>Contrôle</th></tr></thead>
        <tbody>@foreach($analysis['rows'] as $r)
            <tr class="{{ $r['errors'] ? 'table-danger' : ($r['duplicate'] ? 'table-secondary' : ($r['uncertain'] ? 'table-warning' : '')) }}"><td data-l="Ligne">{{ $r['line'] }}</td><td data-l="Opération">{{ $r['operation_date'] }}</td><td data-l="Valeur">{{ $r['value_date'] }}</td><td data-l="Réf.">{{ $r['reference'] }}</td><td data-l="Libellé" class="small">{{ $r['description'] }}</td><td data-l="Montant" class="num">{{ $r['amount'] ? money($r['amount'], $r['currency']) : '' }}</td>
                <td data-l="Contrôle" class="small">{{ implode(' ; ', $r['errors']) }}{{ $r['duplicate'] ? 'Déjà importée' : '' }}{{ $r['uncertain'] ? ' À vérifier : '.implode(', ', $r['uncertain']) : '' }}</td></tr>
        @endforeach</tbody></table></div>
        <div class="card-footer bg-white"><form method="post" action="{{ route('bank.confirm', $batch) }}" data-confirm="Importer les lignes nouvelles et valides ?">@csrf<button class="btn btn-accent">Confirmer l’import</button></form></div></div>
@endif
@if($lines)
    <div class="card"><div class="card-header">Lignes importées @if(can_do('bank.reconcile'))<form method="post" action="{{ route('bank.auto', $batch) }}">@csrf<button class="btn btn-sm btn-primary">Rapprochement automatique (exact et unique)</button></form>@endif</div>
        @include('bank._lines', ['lines' => $lines])<div class="card-body">{{ $lines->links() }}</div></div>
@endif
@endsection
