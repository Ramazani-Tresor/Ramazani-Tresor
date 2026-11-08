@extends('layouts.app')
@section('title', 'Rôles et permissions')
@section('content')
<div class="page-head"><div><h1>Rôles et permissions</h1><div class="sub">Les permissions sont vérifiées par le serveur sur chaque opération. L'administrateur dispose de toutes les permissions.</div></div></div>
<ul class="nav nav-tabs" role="tablist">
    @foreach($roles as $r)<li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#role{{ $r->id }}" type="button">{{ $r->name }} <span class="badge text-bg-light">{{ $r->users_count }}</span></button></li>@endforeach
</ul>
<div class="tab-content card border-top-0 rounded-top-0">
    @foreach($roles as $r)
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="role{{ $r->id }}">
            <form class="card-body" method="post" action="{{ route('roles.update', $r) }}">@csrf @method('PUT')
                <p class="text-muted-2">{{ $r->description }}</p>
                @php $has = $r->permissions->pluck('id')->all(); $locked = $r->slug === \App\Models\Role::ADMIN; @endphp
                <div class="row g-3">
                    @foreach($permissions as $group => $perms)
                        <div class="col-md-6 col-xl-4"><div class="border rounded-3 p-3 h-100"><div class="fw-600 text-navy mb-2">{{ $group }}</div>
                            @foreach($perms as $p)
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p->id }}" id="p{{ $r->id }}_{{ $p->id }}" @checked($locked || in_array($p->id, $has)) @disabled($locked)>
                                    <label class="form-check-label small" for="p{{ $r->id }}_{{ $p->id }}">{{ $p->name }}</label></div>
                            @endforeach
                        </div></div>
                    @endforeach
                </div>
                @unless($locked)<button class="btn btn-primary mt-3" data-confirm="Enregistrer les permissions du rôle « {{ $r->name }} » ? Elles s'appliquent immédiatement à {{ $r->users_count }} utilisateur(s).">Enregistrer</button>@endunless
            </form>
        </div>
    @endforeach
</div>
@endsection
