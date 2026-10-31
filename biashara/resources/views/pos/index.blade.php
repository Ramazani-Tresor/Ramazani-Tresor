@extends('layouts.app')
@section('title', 'Point de vente')
@section('content')
<div id="pos-app" data-config='@json($config)' data-methods='@json($paymentMethods)' data-session="{{ $session ? 1 : 0 }}"
     data-customer-store="{{ $config['canCreateCustomer'] ? route('customers.store') : '' }}">

    <div class="page-head">
        <div>
            <h1><i class="bi bi-cart3 me-2"></i>Point de vente</h1>
            <div class="sub">
                @if($session)
                    Caisse <strong>{{ $session->number }}</strong> ouverte depuis {{ $session->opened_at->format('H:i') }}
                @else
                    <span class="text-danger fw-600"><i class="bi bi-exclamation-triangle"></i> Aucune caisse ouverte</span>
                @endif
                · Catalogue : <span id="catalogAge">—</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @unless($session)
                <a href="{{ route('cash.index') }}" class="btn btn-warning"><i class="bi bi-unlock me-1"></i>Ouvrir ma caisse</a>
            @endunless
            <button class="btn btn-light" id="btnQueue" type="button" data-bs-toggle="offcanvas" data-bs-target="#queuePanel">
                <i class="bi bi-cloud-arrow-up me-1"></i>Ventes hors ligne <span class="badge text-bg-warning" id="queueBadge">0</span>
            </button>
            <button class="btn btn-light" id="btnRefresh" type="button" title="Recharger le catalogue"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
    </div>

    <div id="offlineBanner" class="alert alert-warning d-none">
        <i class="bi bi-wifi-off me-1"></i><strong>Mode hors ligne.</strong>
        Les ventes sont enregistrées sur cet appareil comme <strong>provisoires</strong> et seront validées par le serveur à la synchronisation.
        Le stock affiché peut ne pas refléter les ventes des autres caisses : une vente pourra être signalée « en conflit ».
    </div>

    <div class="pos">
        <section>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-upc-scan"></i></span>
                        <input id="posSearch" type="search" class="form-control" autocomplete="off" autofocus
                               placeholder="Nom, référence ou code-barres (scanner : lecture directe)" aria-label="Rechercher un article">
                    </div>
                    <div class="pos-cats mt-3" id="posCats">
                        <button class="btn btn-sm btn-primary" data-cat="">Tout</button>
                        @foreach($categories as $c)
                            <button class="btn btn-sm btn-outline-primary" data-cat="{{ $c->id }}">{{ $c->name }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="pos-products" id="posProducts" aria-live="polite"></div>
            <div class="empty d-none" id="posEmpty"><i class="bi bi-search"></i>Aucun article ne correspond.</div>
        </section>

        <aside class="card cart">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person"></i>
                <div class="flex-grow-1 position-relative">
                    <input id="customerSearch" class="form-control form-control-sm" placeholder="Client de passage — rechercher un client…" autocomplete="off">
                    <div id="customerResults" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1050"></div>
                </div>
                <button class="btn btn-sm btn-light d-none" id="customerClear" title="Retirer le client"><i class="bi bi-x-lg"></i></button>
                @if($config['canCreateCustomer'])
                    <button class="btn btn-sm btn-light" id="customerNew" title="Nouveau client" data-bs-toggle="modal" data-bs-target="#customerModal"><i class="bi bi-person-plus"></i></button>
                @endif
            </div>
            <div class="cart-lines" id="cartLines">
                <div class="empty" id="cartEmpty"><i class="bi bi-basket"></i>Panier vide.<br><small>Scannez ou touchez un article.</small></div>
            </div>
            <div class="px-3 py-2 small border-top">
                <div class="d-flex justify-content-between"><span>Sous-total</span><span class="money" id="tSubtotal">0</span></div>
                <div class="d-flex justify-content-between text-success"><span>Remises</span><span class="money" id="tDiscount">0</span></div>
                <div class="d-flex justify-content-between"><span>Taxes</span><span class="money" id="tTax">0</span></div>
            </div>
            <div class="cart-total d-flex justify-content-between align-items-center">
                <span class="text-uppercase small" style="color:#9DB4D6">Net à payer</span>
                <span class="big" id="tTotal">0</span>
            </div>
            <div class="p-3 d-grid gap-2">
                <button class="btn btn-primary pay-btn" id="btnPay" disabled><i class="bi bi-credit-card-2-front me-2"></i>Encaisser <small class="fw-normal">(F9)</small></button>
                <button class="btn btn-outline-danger" id="btnClear" type="button"><i class="bi bi-trash me-1"></i>Vider le panier</button>
            </div>
        </aside>
    </div>
</div>

{{-- Paiement --}}
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payTitle">Encaissement — <span id="payTotal" class="money"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div id="payError" class="alert alert-danger d-none"></div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-600">Mode de paiement</label>
                        <div class="d-grid gap-2" style="grid-template-columns:repeat(2,1fr)" id="payMethods"></div>
                        <div id="currencyBox" class="mt-2 d-none">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="paySecondary">
                                <label class="form-check-label" for="paySecondary">Montant remis en <span class="sec-code"></span> (taux : 1 <span class="sec-code"></span> = <span id="rateLabel"></span>)</label>
                            </div>
                        </div>
                        <label class="form-label fw-600 mt-3" for="payAmount">Montant reçu</label>
                        <div class="input-group input-group-lg">
                            <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="payAmount" autocomplete="off">
                            <span class="input-group-text" id="payCurrency"></span>
                        </div>
                        <input type="text" class="form-control form-control-sm mt-2" id="payRef" placeholder="Référence de transaction (Mobile Money, virement…)" maxlength="100">
                        <div class="d-flex flex-wrap gap-2 mt-2" id="quickAmounts"></div>
                        <button class="btn btn-outline-primary w-100 mt-2" id="btnAddPayment" type="button"><i class="bi bi-plus-lg me-1"></i>Ajouter ce paiement (paiement mixte)</button>
                    </div>
                    <div class="col-md-5">
                        <div class="keypad mb-3 d-none d-md-grid" id="keypad">
                            @foreach(['7','8','9','4','5','6','1','2','3','00','0','⌫'] as $k)<button type="button" class="btn btn-light" data-k="{{ $k }}">{{ $k }}</button>@endforeach
                        </div>
                        <ul class="list-group mb-2" id="payList"></ul>
                        <div class="bg-sky rounded-3 p-3">
                            <div class="d-flex justify-content-between"><span>Net à payer</span><strong class="money" id="pTotal"></strong></div>
                            <div class="d-flex justify-content-between"><span>Reçu</span><strong class="money" id="pReceived"></strong></div>
                            <div class="d-flex justify-content-between fs-5 text-success"><span>Monnaie à rendre</span><strong class="money" id="pChange"></strong></div>
                            <div class="d-flex justify-content-between text-danger"><span>Reste dû (crédit)</span><strong class="money" id="pDue"></strong></div>
                        </div>
                        <div class="small text-muted-2 mt-2" id="creditHint"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Retour</button>
                <button type="button" class="btn btn-primary pay-btn px-4" id="btnValidate"><i class="bi bi-check2-circle me-1"></i>Valider la vente <small class="fw-normal">(Entrée)</small></button>
            </div>
        </div>
    </div>
</div>

{{-- Vente terminée --}}
<div class="modal fade" id="doneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="display-5 text-success"><i class="bi bi-check-circle-fill"></i></div>
                <h5 class="mt-2" id="doneTitle">Vente enregistrée</h5>
                <p class="mb-1" id="doneNumber"></p>
                <p class="fs-4 fw-bold text-success mb-1" id="doneChange"></p>
                <p class="text-danger mb-2" id="doneDue"></p>
                <div class="alert alert-warning small d-none" id="doneOffline">Vente provisoire enregistrée hors ligne : elle sera validée à la synchronisation. Le ticket porte la mention « provisoire ».</div>
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-3" id="doneActions"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary w-100 pay-btn" data-bs-dismiss="modal" id="btnNext">Nouvelle vente <small class="fw-normal">(Entrée)</small></button></div>
        </div>
    </div>
</div>

{{-- Nouveau client --}}
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="customerForm" data-allow-resubmit>
            <div class="modal-header"><h5 class="modal-title">Nouveau client</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
            <div class="modal-body">
                <div id="customerError" class="alert alert-danger d-none"></div>
                <div class="mb-2"><label class="form-label">Nom ou raison sociale *</label><input name="name" class="form-control" required maxlength="190"></div>
                <div class="mb-2"><label class="form-label">Téléphone</label><input name="phone" class="form-control" maxlength="40"></div>
                <div class="mb-2"><label class="form-label">Adresse</label><input name="address" class="form-control" maxlength="255"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

{{-- File des ventes hors ligne --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="queuePanel" style="width:min(460px,100vw)">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title"><i class="bi bi-cloud-arrow-up me-1"></i>Ventes enregistrées hors ligne</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-muted-2">Dernière synchronisation : <span id="lastSync">jamais</span>. Les ventes « en conflit » sont examinées par un responsable ; elles ne sont jamais modifiées automatiquement.</p>
        <button class="btn btn-primary w-100 mb-3" id="btnSyncNow"><i class="bi bi-arrow-repeat me-1"></i>Synchroniser maintenant</button>
        <div id="queueList"></div>
    </div>
</div>

<div id="localReceipt" class="d-none"></div>
@endsection

@push('scripts')
@php
    $shopInfo = ['name' => setting('shop_name'), 'address' => setting('shop_address'), 'phone' => setting('shop_phone'), 'footer' => setting('receipt_footer')];
@endphp
<script>
    window.BZ_ROUTES = {
        catalog: @json(route('api.pos.catalog')),
        store: @json(route('api.sales.store')),
        customers: @json(route('api.customers')),
        shop: @json($shopInfo),
    };
</script>
<script src="{{ asset('js/pos.js') }}?v=1"></script>
@endpush
