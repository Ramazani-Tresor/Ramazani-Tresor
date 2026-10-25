@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex gap-2" role="alert">
        <i class="bi bi-check-circle-fill"></i><div>{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-octagon-fill"></i><div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <div class="fw-600 mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Veuillez corriger les erreurs suivantes :</div>
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
