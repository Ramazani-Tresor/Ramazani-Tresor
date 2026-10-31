<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Vérification de document</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body><main class="verify-card card"><div class="card-body text-center p-4">
    <img src="{{ asset('images/logo.svg') }}" width="54" alt="">
    <h1 class="h5 mt-3 text-navy">Vérification d’un document</h1>
    <p class="text-muted-2 small">{{ setting('university_name') }}</p>
    @if(!$doc)
        <div class="alert alert-danger">Document inconnu : ce code ne correspond à aucun document émis.</div>
    @else
        <div class="alert alert-{{ $doc->status === 'final' ? 'success' : ($doc->status === 'provisional' ? 'warning' : 'danger') }}">
            {{ $doc->status === 'final' ? 'Document authentique et valide.' : ($doc->status === 'provisional' ? 'Document PROVISOIRE : il ne vaut pas document définitif.' : 'Document ANNULÉ : il n’est plus valable.') }}</div>
        <dl class="row text-start small mb-0">
            <dt class="col-5">Type</dt><dd class="col-7">{{ status_label('document_type', $doc->type) }}</dd>
            <dt class="col-5">Numéro</dt><dd class="col-7">{{ $doc->number }}</dd>
            <dt class="col-5">Émis le</dt><dd class="col-7">{{ $doc->issued_at->format('d/m/Y H:i') }}</dd>
            <dt class="col-5">Étudiant</dt><dd class="col-7">{{ $doc->summary['student_initials'] ?? '—' }}</dd>
            @if(isset($doc->summary['amount']))<dt class="col-5">Montant</dt><dd class="col-7">{{ money($doc->summary['amount'], $doc->summary['currency'] ?? '') }}</dd>@endif
            @if(isset($doc->summary['year']))<dt class="col-5">Année</dt><dd class="col-7">{{ $doc->summary['year'] }}</dd>@endif
        </dl>
    @endif
</div></main></body></html>
