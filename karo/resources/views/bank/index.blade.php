@extends('layouts.app')
@section('title', 'Relevés et rapprochement')
@section('content')
@include('partials.head', ['title' => 'Relevés bancaires et rapprochement', 'sub' => 'La preuve bancaire (relevé) est distincte du paiement enregistré : le rapprochement confirme un paiement, il n’en crée jamais.',
    'actions' => '<a class="btn btn-light" href="'.route('bank.lines').'"><i class="bi bi-list-ul"></i> Lignes de relevé</a><a class="btn btn-light" href="'.route('bank.report').'"><i class="bi bi-clipboard-data"></i> Rapport</a>'])
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><a class="card kpi warn-k text-decoration-none" href="{{ route('bank.lines', ['status' => 'unmatched']) }}"><div class="l">Lignes non rapprochées</div><div class="v">{{ $stats['unmatched'] }}</div></a></div>
    <div class="col-6 col-lg-3"><a class="card kpi text-decoration-none" href="{{ route('bank.lines', ['status' => 'partial']) }}"><div class="l">Partiellement rapprochées</div><div class="v">{{ $stats['partial'] }}</div></a></div>
    <div class="col-6 col-lg-3"><a class="card kpi alert-k text-decoration-none" href="{{ route('bank.lines', ['review' => 'pending']) }}"><div class="l">Extractions à vérifier</div><div class="v">{{ $stats['review'] }}</div></a></div>
    <div class="col-6 col-lg-3"><a class="card kpi text-decoration-none" href="{{ route('payments.index', ['status' => 'unconfirmed']) }}"><div class="l">Paiements validés non confirmés</div><div class="v">{{ $stats['unconfirmed'] }}</div></a></div>
</div>
<div class="row g-3">
    @if(can_do('bank.import'))
    <div class="col-lg-4"><div class="card"><div class="card-header">Importer un relevé</div>
        <form class="card-body" method="post" enctype="multipart/form-data" action="{{ route('bank.upload') }}">@csrf
            <div class="mb-2"><label class="form-label" for="ba">Compte</label><select id="ba" name="bank_account_id" class="form-select" required>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->display() }}</option>@endforeach</select></div>
            <div class="mb-3"><label class="form-label" for="bf">Fichier Excel, CSV ou PDF (20 Mo)</label><input id="bf" type="file" name="file" class="form-control" accept=".xlsx,.csv,.pdf" required></div>
            <button class="btn btn-primary"><i class="bi bi-upload"></i> Déposer</button>
            <p class="small text-muted-2 mt-3 mb-0">PDF : extraction de texte locale ; toutes les lignes extraites d’un PDF sont soumises à vérification humaine. Les PDF scannés (images) ne sont pas pris en charge sans OCR local. Aucun document n’est envoyé à un service externe.</p>
        </form></div></div>
    @endif
    <div class="col-lg-8"><div class="card"><div class="card-header">Lots importés</div>
        <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>Lot</th><th>Compte</th><th>Période couverte</th><th>Lignes</th><th>Statut</th><th></th></tr></thead><tbody>
        @forelse($batches as $b)
            <tr><td data-l="Lot"><a href="{{ route('bank.batches.show', $b) }}">{{ $b->number }}</a><div class="small text-muted-2">{{ \Illuminate\Support\Str::limit($b->original_name, 26) }}</div></td>
                <td data-l="Compte" class="small">{{ $b->bankAccount?->bank->code }} {{ $b->bankAccount?->account_number }}</td>
                <td data-l="Période">{{ $b->period_start?->format('d/m/Y') }} → {{ $b->period_end?->format('d/m/Y') }}</td>
                <td data-l="Lignes" class="small">{{ $b->rows_created }} importées · {{ $b->rows_skipped }} déjà connues · {{ $b->rows_error }} en erreur</td>
                <td data-l="Statut">{{ ['uploaded' => 'Déposé', 'previewed' => 'Prévisualisé', 'processing' => 'En cours', 'completed' => 'Importé', 'failed' => 'Échec', 'cancelled' => 'Annulé'][$b->status] }}</td>
                <td class="nw">@if($b->status === 'completed' && can_do('bank.reconcile'))<form method="post" action="{{ route('bank.auto', $b) }}">@csrf<button class="btn btn-sm btn-light" title="Appliquer les correspondances exactes et uniques">Rapprocher (auto)</button></form>@endif</td></tr>
        @empty<tr><td colspan="6">@include('partials.empty', ['icon' => 'bi-bank', 'text' => 'Aucun relevé importé.'])</td></tr>@endforelse
        </tbody></table></div><div class="card-body">{{ $batches->links() }}</div></div></div>
</div>
@endsection
