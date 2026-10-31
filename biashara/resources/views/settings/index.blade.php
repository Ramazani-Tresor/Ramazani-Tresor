@extends('layouts.app')
@section('title', 'Paramètres')
@section('content')
<div class="page-head"><div><h1>Paramètres</h1><div class="sub">Commerce, devises, reçus, caisse, sécurité et sauvegardes</div></div></div>
<ul class="nav nav-tabs" role="tablist">
    @foreach(['general' => 'Commerce', 'money' => 'Devises et taxes', 'sales' => 'Ventes et stock', 'numbering' => 'Numérotation', 'backup' => 'Sauvegardes'] as $k => $l)
        <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#t-{{ $k }}" type="button">{{ $l }}</button></li>
    @endforeach
</ul>
<div class="tab-content card border-top-0 rounded-top-0 mb-3">
<form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" id="settingsForm">@csrf @method('PUT')
    <div class="tab-pane fade show active card-body" id="t-general">
        <div class="row g-3" style="max-width:900px">
            <div class="col-md-6"><label class="form-label">Nom du commerce *</label><input name="shop_name" class="form-control" value="{{ old('shop_name', $settings['shop_name']) }}" required maxlength="120"></div>
            <div class="col-md-6"><label class="form-label">N° fiscal / RCCM</label><input name="shop_tax_id" class="form-control" value="{{ old('shop_tax_id', $settings['shop_tax_id']) }}" maxlength="80"></div>
            <div class="col-12"><label class="form-label">Adresse</label><input name="shop_address" class="form-control" value="{{ old('shop_address', $settings['shop_address']) }}" maxlength="255"></div>
            <div class="col-md-6"><label class="form-label">Téléphone(s)</label><input name="shop_phone" class="form-control" value="{{ old('shop_phone', $settings['shop_phone']) }}" maxlength="80"></div>
            <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="shop_email" class="form-control" value="{{ old('shop_email', $settings['shop_email']) }}" maxlength="150"></div>
            <div class="col-md-6"><label class="form-label">Logo (reçus)</label><input type="file" name="logo" class="form-control" accept="image/*">@if($settings['shop_logo'])<img src="{{ asset('storage/'.$settings['shop_logo']) }}" alt="" class="mt-2" style="max-height:50px">@endif</div>
            <div class="col-md-6"><label class="form-label">Fuseau horaire *</label><select name="timezone" class="form-select">@foreach($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $settings['timezone']) === $tz)>{{ $tz }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Format de reçu par défaut *</label><select name="receipt_format" class="form-select">@foreach(['80' => 'Ticket 80 mm', '58' => 'Ticket 58 mm', 'a4' => 'A4'] as $k => $v)<option value="{{ $k }}" @selected($settings['receipt_format'] == $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Message de remerciement</label><input name="receipt_footer" class="form-control" value="{{ old('receipt_footer', $settings['receipt_footer']) }}" maxlength="255"></div>
        </div>
    </div>
    <div class="tab-pane fade card-body" id="t-money">
        <div class="row g-3" style="max-width:900px">
            <div class="col-md-3"><label class="form-label">Devise principale *</label><input name="currency_code" class="form-control text-uppercase" value="{{ old('currency_code', $settings['currency_code']) }}" required maxlength="3"></div>
            <div class="col-md-3"><label class="form-label">Symbole *</label><input name="currency_symbol" class="form-control" value="{{ old('currency_symbol', $settings['currency_symbol']) }}" required maxlength="5"></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="secondary_currency_enabled" value="1" id="sec" @checked($settings['secondary_currency_enabled'])><label class="form-check-label" for="sec">Accepter une seconde devise à l'encaissement</label></div></div>
            <div class="col-md-3"><label class="form-label">Seconde devise</label><input name="secondary_currency_code" class="form-control text-uppercase" value="{{ old('secondary_currency_code', $settings['secondary_currency_code']) }}" maxlength="3"></div>
            <div class="col-md-3"><label class="form-label">Symbole</label><input name="secondary_currency_symbol" class="form-control" value="{{ old('secondary_currency_symbol', $settings['secondary_currency_symbol']) }}" maxlength="5"></div>
            <div class="col-md-6"><label class="form-label">Taux : 1 unité de la seconde devise = … devise principale *</label><input name="exchange_rate" class="form-control text-end" inputmode="decimal" value="{{ old('exchange_rate', $settings['exchange_rate']) }}" required></div>
            <div class="col-12 small text-muted-2">Le taux appliqué et la devise réellement remise sont enregistrés avec chaque paiement. Les montants sont toujours convertis dans la devise principale avant addition : deux devises ne sont jamais additionnées directement.</div>
        </div>
    </div>
    <div class="tab-pane fade card-body" id="t-sales">
        <div class="row g-3" style="max-width:900px">
            <div class="col-md-4"><label class="form-label">Remise maximale (%) *</label><input name="max_discount_percent" class="form-control text-end" inputmode="decimal" value="{{ old('max_discount_percent', $settings['max_discount_percent']) }}" required><div class="form-text">Pour les utilisateurs autorisés à remiser (hors administrateur).</div></div>
            <div class="col-md-4"><label class="form-label">Seuil d'alerte par défaut *</label><input name="default_alert_threshold" class="form-control text-end" inputmode="decimal" value="{{ old('default_alert_threshold', $settings['default_alert_threshold']) }}" required></div>
            <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_negative_stock" value="1" id="neg" @checked($settings['allow_negative_stock'])><label class="form-check-label" for="neg">Autoriser le stock négatif (déconseillé)</label></div></div>
            <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="offline_sales_enabled" value="1" id="off" @checked($settings['offline_sales_enabled'])><label class="form-check-label" for="off">Autoriser les ventes hors ligne (provisoires)</label></div></div>
            <div class="col-md-4"><label class="form-label">Plafond d'une vente hors ligne *</label><input name="offline_max_sale_amount" class="form-control text-end" inputmode="decimal" value="{{ old('offline_max_sale_amount', $settings['offline_max_sale_amount']) }}" required><div class="form-text">0 = sans plafond.</div></div>
        </div>
    </div>
    <div class="card-body border-top"><button class="btn btn-primary" form="settingsForm"><i class="bi bi-save me-1"></i>Enregistrer les paramètres</button></div>
</form>
    <div class="tab-pane fade card-body" id="t-numbering">
        <form method="post" action="{{ route('settings.sequences') }}" style="max-width:760px">@csrf @method('PUT')
            <table class="table table-sm"><thead><tr><th>Document</th><th>Préfixe</th><th>Chiffres</th><th>Prochain numéro</th></tr></thead><tbody>
            @foreach($sequences as $s)<tr><td>{{ $s->label }}</td><td><input name="sequences[{{ $s->id }}][prefix]" class="form-control form-control-sm" value="{{ $s->prefix }}" maxlength="20"></td><td><input type="number" name="sequences[{{ $s->id }}][padding]" class="form-control form-control-sm" value="{{ $s->padding }}" min="3" max="10"></td><td class="small">{{ $s->prefix }}{{ $s->yearly_reset ? '-'.now()->year : '' }}-{{ str_pad($s->next_number, $s->padding, '0', STR_PAD_LEFT) }}</td></tr>@endforeach
            </tbody></table>
            <button class="btn btn-primary">Enregistrer la numérotation</button>
            <div class="form-text">Les compteurs ne peuvent pas être modifiés manuellement : un numéro attribué n'est jamais réutilisé.</div>
        </form>
    </div>
    <div class="tab-pane fade card-body" id="t-backup">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label">Chemin de mysqldump</label>
                    <div class="input-group"><input name="backup_mysqldump_path" form="settingsForm" class="form-control" value="{{ $settings['backup_mysqldump_path'] }}" maxlength="255"><button class="btn btn-light" form="settingsForm">Enregistrer</button></div>
                    <div class="form-text">XAMPP : <code>C:\xampp\mysql\bin\mysqldump.exe</code></div>
                </div>
                <form method="post" action="{{ route('settings.backup') }}" data-confirm="Créer une sauvegarde complète de la base maintenant ?">@csrf<button class="btn btn-primary"><i class="bi bi-database-down me-1"></i>Créer une sauvegarde</button></form>
                <p class="small text-muted-2 mt-2">Commande équivalente : <code>php artisan app:backup</code> (planifiable avec le Planificateur de tâches Windows). Copiez régulièrement les sauvegardes hors de l'ordinateur (clé USB, cloud). Procédure de restauration : voir <code>docs/INSTALLATION.md</code>.</p>
            </div>
            <div class="col-lg-6">
                <table class="table table-sm"><thead><tr><th>Fichier</th><th>Date</th><th class="num">Taille</th><th></th></tr></thead><tbody>
                @forelse($backups as $b)<tr><td class="small">{{ $b['name'] }}</td><td class="small">{{ $b['date'] }}</td><td class="num small">{{ number_format($b['size'] / 1048576, 2, ',', ' ') }} Mo</td><td><a href="{{ route('settings.backup.download', $b['name']) }}" class="btn btn-sm btn-light"><i class="bi bi-download"></i></a></td></tr>
                @empty<tr><td colspan="4" class="empty">Aucune sauvegarde.</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Modes de paiement</div>
        <ul class="list-group list-group-flush">@foreach($paymentMethods as $m)
            <li class="list-group-item d-flex justify-content-between align-items-center"><span>{{ $m->name }} <small class="text-muted-2">({{ $m->kind === 'cash' ? 'espèces' : 'électronique' }})</small></span>
                <form method="post" action="{{ route('settings.payment-methods.toggle', $m) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $m->is_active ? 'btn-light' : 'btn-outline-success' }}">{{ $m->is_active ? 'Désactiver' : 'Activer' }}</button></form></li>
        @endforeach</ul>
        <form class="card-body d-flex gap-2" method="post" action="{{ route('settings.payment-methods.store') }}">@csrf<input name="name" class="form-control form-control-sm" placeholder="Nouveau mode" required maxlength="80"><select name="kind" class="form-select form-select-sm" style="max-width:130px"><option value="electronic">Électronique</option><option value="cash">Espèces</option></select><button class="btn btn-sm btn-primary">Ajouter</button></form>
    </div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Taxes</div>
        <ul class="list-group list-group-flush">@forelse($taxes as $t)
            <li class="list-group-item d-flex justify-content-between align-items-center"><span>{{ $t->name }} — {{ rtrim(rtrim($t->rate, '0'), '.') }} %</span>
                <form method="post" action="{{ route('settings.taxes.toggle', $t) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $t->is_active ? 'btn-light' : 'btn-outline-success' }}">{{ $t->is_active ? 'Désactiver' : 'Activer' }}</button></form></li>
        @empty<li class="list-group-item small text-muted-2">Aucune taxe configurée : aucune taxe n'est appliquée par défaut. Saisissez uniquement les taux qui vous sont applicables.</li>@endforelse</ul>
        <form class="card-body d-flex gap-2" method="post" action="{{ route('settings.taxes.store') }}">@csrf<input name="name" class="form-control form-control-sm" placeholder="Nom (ex. TVA)" required maxlength="80"><input name="rate" class="form-control form-control-sm text-end" placeholder="Taux %" required style="max-width:90px"><button class="btn btn-sm btn-primary">Ajouter</button></form>
    </div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Catégories de dépenses</div>
        <div class="card-body small">@foreach($expenseCategories as $c)<span class="badge text-bg-light border me-1 mb-1">{{ $c->name }}</span>@endforeach</div>
        <form class="card-body d-flex gap-2 pt-0" method="post" action="{{ route('settings.expense-categories.store') }}">@csrf<input name="name" class="form-control form-control-sm" placeholder="Nouvelle catégorie" required maxlength="120"><button class="btn btn-sm btn-primary">Ajouter</button></form>
    </div></div>
</div>
@endsection
