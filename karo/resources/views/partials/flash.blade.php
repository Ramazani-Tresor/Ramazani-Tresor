@foreach(['success' => ['success', 'check-circle-fill'], 'error' => ['danger', 'exclamation-octagon-fill'], 'warning' => ['warning', 'exclamation-triangle-fill'], 'info' => ['info', 'info-circle-fill']] as $k => [$cls, $ico])
    @if(session($k))
        <div class="alert alert-{{ $cls }} alert-dismissible fade show d-flex gap-2" role="{{ $k === 'error' ? 'alert' : 'status' }}"><i class="bi bi-{{ $ico }}"></i><div>{{ session($k) }}</div><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
    @endif
@endforeach
@if($errors->any())
    <div class="alert alert-danger" role="alert"><div class="fw-600"><i class="bi bi-exclamation-octagon-fill me-1"></i>Le formulaire contient des erreurs :</div>
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
