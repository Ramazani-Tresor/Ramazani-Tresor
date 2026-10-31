/*
 * Biashara — point de vente.
 * Calculs monétaires en entiers (BigInt, centimes / millièmes de quantité) : aucun nombre flottant,
 * mêmes règles d'arrondi que le serveur (demi supérieur). Le serveur reste l'autorité :
 * il recalcule tout et refuse une vente incohérente.
 */
(function () {
    'use strict';
    const root = document.getElementById('pos-app');
    if (!root) return;
    const cfg = JSON.parse(root.dataset.config);
    const methods = JSON.parse(root.dataset.methods);
    const R = window.BZ_ROUTES;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---------- Arithmétique décimale exacte ----------
    function parseScaled(value, scale) {
        let s = String(value ?? '').trim().replace(/\s| /g, '').replace(',', '.');
        if (s === '' || s === '.' || s === '-') return 0n;
        if (!/^-?\d*(\.\d*)?$/.test(s)) return null;
        const neg = s.startsWith('-'); if (neg) s = s.slice(1);
        let [i, d = ''] = s.split('.');
        const extra = d.length > scale ? d[scale] : '0';
        d = (d + '0'.repeat(scale)).slice(0, scale);
        let n = BigInt((i || '0') + d);
        if (extra >= '5') n += 1n;
        return neg ? -n : n;
    }
    const cents = (v) => parseScaled(v, 2);
    const milli = (v) => parseScaled(v, 3);
    const divRound = (a, b) => { const q = a / b, r = a % b; return (r * 2n >= b) ? q + 1n : q; }; // a, b ≥ 0
    const toDec = (n, scale) => { const neg = n < 0n; let s = (neg ? -n : n).toString().padStart(scale + 1, '0'); return (neg ? '-' : '') + s.slice(0, -scale) + '.' + s.slice(-scale); };
    function fmt(c, symbol = cfg.symbol) {
        const neg = c < 0n; const s = (neg ? -c : c).toString().padStart(3, '0');
        const int = s.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return (neg ? '-' : '') + int + ',' + s.slice(-2) + (symbol ? ' ' + symbol : '');
    }
    const fmtQty = (m) => { const s = toDec(m, 3).replace(/\.?0+$/, ''); return s.replace('.', ','); };
    const rateE4 = parseScaled(cfg.exchangeRate, 4) || 10000n;

    // ---------- État ----------
    let catalog = [];
    let category = '';
    let cart = []; // {p, q (milli), disc (cents)}
    let customer = null;
    let saleUuid = BzOffline.uuid();
    let payments = [];
    let currentMethod = methods[0] || null;
    let submitting = false;

    // ---------- Catalogue ----------
    async function loadCatalog(force = false) {
        if (BzOffline.isOnline() || force) {
            try {
                const r = await fetch(R.catalog, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (r.ok) {
                    const d = await r.json();
                    catalog = d.products;
                    await BzOffline.saveCatalog(catalog, d.generated_at);
                    setCatalogAge(d.generated_at, false);
                    render();
                    return;
                }
            } catch (e) { BzOffline.setOnline(false); }
        }
        catalog = await BzOffline.getCatalog();
        const at = await BzOffline.getMeta('catalog_at');
        setCatalogAge(at, true);
        render();
    }
    function setCatalogAge(at, cached) {
        const el = $('catalogAge');
        if (!at) { el.textContent = 'non synchronisé'; return; }
        const d = new Date(at);
        el.innerHTML = (cached ? '<span class="text-warning fw-600">copie locale du ' : 'à jour (') + d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) + (cached ? '</span>' : ')');
    }

    function filtered() {
        const t = $('posSearch').value.trim().toLowerCase();
        return catalog.filter(p => (!category || String(p.category_id) === category)
            && (!t || p.name.toLowerCase().includes(t) || p.reference.toLowerCase().includes(t) || (p.barcode && p.barcode === t)));
    }

    function render() {
        const list = filtered().slice(0, 120);
        $('posEmpty').classList.toggle('d-none', list.length > 0);
        $('posProducts').innerHTML = list.map(p => {
            const out = p.type === 'product' && milli(p.stock) <= 0n;
            const stock = p.type === 'service' ? 'Service' : 'Stock : ' + fmtQty(milli(p.stock)) + ' ' + esc(p.unit || '');
            return `<button type="button" class="pos-product ${out ? 'out' : ''}" data-id="${p.id}">
                <span class="n">${esc(p.name)}</span><span class="s">${esc(p.reference)}</span>
                <span><span class="p d-block">${fmt(cents(p.price))}</span><span class="s">${stock}</span></span></button>`;
        }).join('');
    }

    // ---------- Panier ----------
    function add(p, q = 1000n) {
        if (!p) return;
        const line = cart.find(l => l.p.id === p.id);
        const newQ = (line ? line.q : 0n) + q;
        if (p.type === 'product' && newQ > milli(p.stock)) {
            BzToast(`Stock indiqué insuffisant pour « ${p.name} » (${fmtQty(milli(p.stock))}). ${BzOffline.isOnline() ? 'Le serveur refusera la vente.' : 'Hors ligne : la vente sera vérifiée à la synchronisation.'}`, 'warning');
        }
        if (line) line.q = newQ; else cart.push({ p, q, disc: 0n });
        renderCart();
    }

    function lineCalc(l) {
        const price = cents(l.p.price);
        const gross = divRound(l.q * price, 1000n);
        const disc = l.disc > gross ? gross : l.disc;
        const net = gross - disc;
        const rate = parseScaled(l.p.tax_rate || '0', 4);
        const tax = divRound(net * rate, 1000000n);
        return { gross, disc, tax, total: net + tax };
    }
    function totals() {
        let sub = 0n, disc = 0n, tax = 0n, total = 0n;
        cart.forEach(l => { const c = lineCalc(l); sub += c.gross; disc += c.disc; tax += c.tax; total += c.total; });
        return { sub, disc, tax, total };
    }

    function renderCart() {
        const box = $('cartLines');
        box.querySelectorAll('.cart-line').forEach(e => e.remove());
        $('cartEmpty').classList.toggle('d-none', cart.length > 0);
        cart.forEach((l, i) => {
            const c = lineCalc(l);
            const div = document.createElement('div');
            div.className = 'cart-line';
            div.innerHTML = `<div><div class="fw-600 small">${esc(l.p.name)}</div>
                    <div class="small text-muted-2">${fmt(cents(l.p.price))} / ${esc(l.p.unit || 'u')}${c.disc > 0n ? ` · <span class="text-success">remise ${fmt(c.disc)}</span>` : ''}${c.tax > 0n ? ` · taxe ${fmt(c.tax)}` : ''}</div>
                    <div class="qty mt-1">
                        <button class="btn btn-light" data-act="dec" data-i="${i}" aria-label="Diminuer">−</button>
                        <input class="form-control form-control-sm" data-act="qty" data-i="${i}" value="${fmtQty(l.q)}" inputmode="decimal" aria-label="Quantité">
                        <button class="btn btn-light" data-act="inc" data-i="${i}" aria-label="Augmenter">+</button>
                        ${cfg.canDiscount ? `<button class="btn btn-light" data-act="disc" data-i="${i}" title="Remise"><i class="bi bi-percent"></i></button>` : ''}
                    </div></div>
                <div class="text-end"><div class="fw-bold money">${fmt(c.total)}</div>
                    <button class="btn btn-link text-danger p-0 mt-2" data-act="del" data-i="${i}" title="Retirer"><i class="bi bi-x-circle"></i></button></div>`;
            box.appendChild(div);
        });
        const t = totals();
        $('tSubtotal').textContent = fmt(t.sub);
        $('tDiscount').textContent = t.disc > 0n ? '−' + fmt(t.disc) : fmt(0n);
        $('tTax').textContent = fmt(t.tax);
        $('tTotal').textContent = fmt(t.total);
        $('btnPay').disabled = cart.length === 0;
    }

    $('cartLines').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]'); if (!b || b.tagName === 'INPUT') return;
        const l = cart[Number(b.dataset.i)]; if (!l) return;
        const step = 1000n;
        if (b.dataset.act === 'inc') l.q += step;
        if (b.dataset.act === 'dec') l.q = l.q > step ? l.q - step : l.q;
        if (b.dataset.act === 'del') cart.splice(Number(b.dataset.i), 1);
        if (b.dataset.act === 'disc') {
            const c = lineCalc(l);
            const v = prompt(`Remise sur « ${l.p.name} » (montant en ${cfg.symbol}, ou pourcentage suivi de %). Plafond : ${cfg.maxDiscount} %`, '');
            if (v !== null) {
                let d = v.trim().endsWith('%') ? divRound(c.gross * (parseScaled(v.replace('%', ''), 2) || 0n), 10000n) : (cents(v) || 0n);
                if (d < 0n) d = 0n;
                l.disc = d > c.gross ? c.gross : d;
            }
        }
        renderCart();
    });
    $('cartLines').addEventListener('change', (e) => {
        if (e.target.dataset.act !== 'qty') return;
        const l = cart[Number(e.target.dataset.i)];
        const q = milli(e.target.value);
        if (q === null || q <= 0n) { BzToast('Quantité invalide.', 'danger'); }
        else if (!l.p.decimal && q % 1000n !== 0n) { BzToast(`« ${l.p.name} » se vend uniquement en quantités entières.`, 'danger'); }
        else l.q = q;
        renderCart();
    });

    $('posProducts').addEventListener('click', (e) => {
        const b = e.target.closest('.pos-product'); if (!b) return;
        add(catalog.find(p => p.id === Number(b.dataset.id)));
    });
    $('posCats').addEventListener('click', (e) => {
        const b = e.target.closest('[data-cat]'); if (!b) return;
        category = b.dataset.cat;
        $('posCats').querySelectorAll('.btn').forEach(x => x.classList.replace('btn-primary', 'btn-outline-primary'));
        b.classList.replace('btn-outline-primary', 'btn-primary');
        render();
    });
    $('posSearch').addEventListener('input', render);
    // Scanner code-barres : il « tape » le code puis Entrée.
    $('posSearch').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const t = e.target.value.trim();
        if (!t) return;
        const exact = catalog.find(p => p.barcode === t) || catalog.find(p => p.reference.toLowerCase() === t.toLowerCase());
        const list = filtered();
        const p = exact || (list.length === 1 ? list[0] : null);
        if (p) { add(p); e.target.value = ''; render(); }
        else BzToast(list.length ? 'Plusieurs articles correspondent : touchez le bon article.' : `Aucun article pour « ${t} ».`, 'warning');
    });
    $('btnClear').addEventListener('click', () => { if (cart.length && confirm('Vider le panier ?')) resetSale(); });
    $('btnRefresh').addEventListener('click', () => loadCatalog(true));

    // ---------- Clients ----------
    let custTimer = null;
    $('customerSearch').addEventListener('input', (e) => {
        clearTimeout(custTimer);
        const q = e.target.value.trim();
        custTimer = setTimeout(async () => {
            let list = [];
            if (BzOffline.isOnline()) {
                try {
                    const r = await fetch(R.customers + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    if (r.ok) list = await r.json();
                    const cache = (await BzOffline.getMeta('customers')) || [];
                    const merged = [...list, ...cache.filter(c => !list.some(x => x.id === c.id))].slice(0, 300);
                    BzOffline.setMeta('customers', merged);
                } catch (err) { /* hors ligne */ }
            }
            if (!list.length) {
                const cache = (await BzOffline.getMeta('customers')) || [];
                list = cache.filter(c => c.name.toLowerCase().includes(q.toLowerCase()) || (c.phone || '').includes(q)).slice(0, 15);
            }
            const box = $('customerResults');
            box.innerHTML = list.map(c => `<button type="button" class="list-group-item list-group-item-action" data-id="${c.id}">${esc(c.name)} <small class="text-muted">${esc(c.phone || '')}</small></button>`).join('')
                || '<div class="list-group-item small text-muted">Aucun client trouvé.</div>';
            box.classList.remove('d-none');
            box.onclick = (ev) => { const b = ev.target.closest('[data-id]'); if (!b) return; setCustomer(list.find(c => c.id === Number(b.dataset.id))); };
        }, 250);
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('#customerResults, #customerSearch')) $('customerResults').classList.add('d-none'); });
    function setCustomer(c) {
        customer = c || null;
        $('customerSearch').value = c ? c.name : '';
        $('customerSearch').readOnly = !!c;
        $('customerClear').classList.toggle('d-none', !c);
        $('customerResults').classList.add('d-none');
    }
    $('customerClear').addEventListener('click', () => setCustomer(null));
    $('customerForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const body = Object.fromEntries(new FormData(form).entries());
        const r = await BzOffline.api(root.dataset.customerStore.replace(document.body.dataset.base || '', ''), body).catch(() => null);
        if (r && r.ok) {
            setCustomer(r.data);
            bootstrap.Modal.getInstance($('customerModal')).hide();
            form.reset();
        } else {
            $('customerError').textContent = r && r.data && r.data.message ? r.data.message : 'Création impossible hors ligne.';
            $('customerError').classList.remove('d-none');
        }
    });

    // ---------- Paiement ----------
    const payModal = new bootstrap.Modal($('payModal'));
    function renderMethods() {
        $('payMethods').innerHTML = methods.map(m => `<button type="button" class="btn btn-outline-primary pay-method ${currentMethod && m.id === currentMethod.id ? 'active' : ''}" data-id="${m.id}">
            <i class="bi ${m.kind === 'cash' ? 'bi-cash-stack' : (m.code === 'mobile_money' ? 'bi-phone' : 'bi-bank')} me-1"></i>${esc(m.name)}</button>`).join('');
    }
    $('payMethods').addEventListener('click', (e) => {
        const b = e.target.closest('[data-id]'); if (!b) return;
        currentMethod = methods.find(m => m.id === Number(b.dataset.id));
        if (currentMethod.kind !== 'cash') $('paySecondary').checked = false;
        renderMethods(); prefillAmount(); refreshPay();
    });
    if (cfg.secondaryEnabled) {
        $('currencyBox').classList.remove('d-none');
        document.querySelectorAll('.sec-code').forEach(e => { e.textContent = cfg.secondaryCode; });
        $('rateLabel').textContent = fmt(rateE4 / 100n);
        $('paySecondary').addEventListener('change', () => { prefillAmount(); refreshPay(); });
    }
    const secondary = () => cfg.secondaryEnabled && $('paySecondary').checked;
    const toMain = (c) => secondary() ? divRound(c * rateE4, 10000n) : c;
    const paidSoFar = () => payments.reduce((s, p) => s + p.cents, 0n);

    function prefillAmount() {
        const rest = totals().total - paidSoFar();
        const v = rest > 0n ? rest : 0n;
        $('payAmount').value = secondary() ? toDec(divRound(v * 10000n, rateE4), 2).replace('.', ',') : toDec(v, 2).replace('.', ',');
        $('payCurrency').textContent = secondary() ? cfg.secondarySymbol : cfg.symbol;
        const rest2 = v;
        const sugg = [rest2];
        [1000n, 5000n, 10000n, 20000n, 50000n].forEach(step => { const s = step * 100n; const up = ((rest2 + s - 1n) / s) * s; if (up > rest2 && !sugg.includes(up)) sugg.push(up); });
        $('quickAmounts').innerHTML = secondary() ? '' : sugg.slice(0, 5).map(c => `<button type="button" class="btn btn-sm btn-light" data-c="${c}">${fmt(c)}</button>`).join('');
    }
    $('quickAmounts').addEventListener('click', (e) => { const b = e.target.closest('[data-c]'); if (b) { $('payAmount').value = toDec(BigInt(b.dataset.c), 2).replace('.', ','); refreshPay(); } });
    $('keypad').addEventListener('click', (e) => {
        const b = e.target.closest('[data-k]'); if (!b) return;
        const inp = $('payAmount');
        if (b.dataset.k === '⌫') inp.value = inp.value.slice(0, -1); else inp.value = (inp.dataset.fresh ? '' : inp.value) + b.dataset.k;
        delete inp.dataset.fresh; refreshPay();
    });
    $('payAmount').addEventListener('input', refreshPay);

    function currentInput() {
        const c = cents($('payAmount').value);
        if (c === null || c <= 0n || !currentMethod) return null;
        return {
            method: currentMethod, cents: toMain(c),
            currency: secondary() ? cfg.secondaryCode : cfg.currency, currencyCents: c,
            reference: $('payRef').value.trim() || null,
        };
    }
    function allPayments(includeInput = true) {
        const extra = includeInput ? currentInput() : null;
        return extra ? [...payments, extra] : [...payments];
    }
    function payState(includeInput = true) {
        const list = allPayments(includeInput);
        const total = totals().total;
        const received = list.reduce((s, p) => s + p.cents, 0n);
        const electronic = list.filter(p => p.method.kind !== 'cash').reduce((s, p) => s + p.cents, 0n);
        const cash = received - electronic;
        let change = received > total ? received - total : 0n;
        const due = received < total ? total - received : 0n;
        let error = null;
        if (electronic > total) error = 'Les paiements électroniques dépassent le net à payer : la monnaie se rend uniquement en espèces.';
        else if (change > cash) error = 'La monnaie à rendre dépasse les espèces reçues.';
        else if (due > 0n && !cfg.canCredit) error = 'Paiement insuffisant : vous n\'êtes pas autorisé à vendre à crédit.';
        else if (due > 0n && !customer) error = 'Vente à crédit ou paiement partiel : sélectionnez d\'abord un client.';
        return { list, total, received, change, due, error };
    }
    function refreshPay() {
        const s = payState();
        $('pTotal').textContent = fmt(s.total);
        $('pReceived').textContent = fmt(s.received);
        $('pChange').textContent = fmt(s.change);
        $('pDue').textContent = fmt(s.due);
        $('creditHint').textContent = s.due > 0n ? (s.error || `Le reste dû (${fmt(s.due)}) sera enregistré comme créance de ${customer.name}.`) : '';
        $('payList').innerHTML = payments.map((p, i) => `<li class="list-group-item d-flex justify-content-between align-items-center">
            <span>${esc(p.method.name)}${p.currency !== cfg.currency ? ` (${toDec(p.currencyCents, 2)} ${esc(p.currency)})` : ''}</span>
            <span class="money">${fmt(p.cents)} <button class="btn btn-link text-danger p-0 ms-2" data-del="${i}" aria-label="Retirer"><i class="bi bi-x"></i></button></span></li>`).join('');
    }
    $('payList').addEventListener('click', (e) => { const b = e.target.closest('[data-del]'); if (b) { payments.splice(Number(b.dataset.del), 1); prefillAmount(); refreshPay(); } });
    $('btnAddPayment').addEventListener('click', () => {
        const p = currentInput(); if (!p) return;
        payments.push(p); $('payRef').value = ''; prefillAmount(); refreshPay();
    });

    $('btnPay').addEventListener('click', openPay);
    function openPay() {
        if (!cart.length) return;
        payments = [];
        currentMethod = methods.find(m => m.kind === 'cash') || methods[0];
        $('payError').classList.add('d-none');
        $('payTotal').textContent = fmt(totals().total);
        renderMethods(); prefillAmount(); refreshPay();
        $('payAmount').dataset.fresh = '1';
        payModal.show();
        setTimeout(() => { $('payAmount').focus(); $('payAmount').select(); }, 300);
    }

    $('btnValidate').addEventListener('click', validateSale);
    $('payModal').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.id !== 'payRef') { e.preventDefault(); validateSale(); } });

    function buildPayload(s) {
        return {
            client_uuid: saleUuid,
            customer_id: customer ? customer.id : null,
            device_id: BzOffline.deviceId(),
            items: cart.map(l => ({ product_id: l.p.id, quantity: toDec(l.q, 3), discount_amount: toDec(lineCalc(l).disc, 2), unit_price: toDec(cents(l.p.price), 2) })),
            payments: s.list.map(p => ({
                payment_method_id: p.method.id, amount: toDec(p.cents, 2), currency: p.currency,
                currency_amount: toDec(p.currencyCents, 2), reference: p.reference,
            })),
        };
    }

    async function validateSale() {
        if (submitting) return; // anti double clic
        const s = payState();
        if (s.error) { showPayError(s.error); return; }
        submitting = true;
        const btn = $('btnValidate');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Validation…';
        const payload = buildPayload(s);
        const display = { total: s.total.toString(), change: s.change.toString(), due: s.due.toString(), customer: customer ? customer.name : null,
            lines: cart.map(l => ({ name: l.p.name, q: l.q.toString(), price: cents(l.p.price).toString(), total: lineCalc(l).total.toString() })) };
        try {
            if (!BzOffline.isOnline()) throw new TypeError('offline');
            const r = await BzOffline.api(R.store.replace(document.body.dataset.base || '', ''), payload);
            if (r.ok) {
                done({ online: true, number: r.data.sale_number, change: cents(r.data.change), due: cents(r.data.due), url: r.data.receipt_url, serverTotal: cents(r.data.total), expected: s.total });
            } else if (r.status >= 500) {
                throw new TypeError('server');
            } else {
                showPayError((r.data && r.data.message) || 'Vente refusée par le serveur.');
                if (r.data && /Stock|prix|Paiement insuffisant/i.test(r.data.message || '')) loadCatalog();
            }
        } catch (err) {
            // Réseau indisponible (ou réponse perdue) : enregistrement local, même identifiant.
            if (!cfg.offlineEnabled) { showPayError('Connexion indisponible et ventes hors ligne désactivées. Réessayez.'); }
            else {
                const cap = cents(cfg.offlineMax || '0');
                if (cap > 0n && s.total > cap) showPayError(`Hors ligne, le montant maximal d'une vente est ${fmt(cap)}.`);
                else {
                    payload.sold_at = new Date().toISOString();
                    await BzOffline.queueSale(payload, display);
                    BzOffline.setOnline(false);
                    done({ online: false, display, change: s.change, due: s.due });
                }
            }
        } finally {
            submitting = false;
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Valider la vente <small class="fw-normal">(Entrée)</small>';
        }
    }
    function showPayError(msg) { const e = $('payError'); e.textContent = msg; e.classList.remove('d-none'); }

    const doneModal = new bootstrap.Modal($('doneModal'));
    function done(res) {
        payModal.hide();
        $('doneTitle').textContent = res.online ? 'Vente enregistrée' : 'Vente enregistrée hors ligne';
        $('doneNumber').textContent = res.online ? 'N° ' + res.number : 'Identifiant : ' + saleUuid.slice(0, 8).toUpperCase();
        $('doneChange').textContent = res.change > 0n ? 'Monnaie à rendre : ' + fmt(res.change) : '';
        $('doneDue').textContent = res.due > 0n ? 'Reste dû : ' + fmt(res.due) : '';
        $('doneOffline').classList.toggle('d-none', res.online);
        const fmtDefault = cfg.receiptFormat;
        if (res.online) {
            $('doneActions').innerHTML = ['80', '58', 'a4'].map(f => `<a class="btn ${f === fmtDefault ? 'btn-primary' : 'btn-light'}" target="_blank" rel="noopener" href="${res.url}?format=${f}&print=1"><i class="bi bi-printer me-1"></i>${f === 'a4' ? 'A4' : f + ' mm'}</a>`).join('')
                + `<a class="btn btn-light" href="${res.url.replace('/recu', '/pdf')}?format=a4"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>`;
            if (res.serverTotal !== res.expected) BzToast('Attention : le total a été recalculé par le serveur (prix mis à jour) : ' + fmt(res.serverTotal), 'warning');
        } else {
            const display = res.display;
            $('doneActions').innerHTML = `<button class="btn btn-primary" id="printLocal"><i class="bi bi-printer me-1"></i>Imprimer le ticket provisoire</button>`;
            $('printLocal').onclick = () => printLocal(display, saleUuid);
        }
        doneModal.show();
        $('doneModal').dataset.next = '1';
    }
    $('doneModal').addEventListener('hidden.bs.modal', () => { if ($('doneModal').dataset.next) { delete $('doneModal').dataset.next; resetSale(); } });

    function resetSale() {
        cart = []; payments = []; setCustomer(null);
        saleUuid = BzOffline.uuid(); // nouvel identifiant uniquement après une vente aboutie ou abandonnée
        renderCart(); $('posSearch').value = ''; render(); $('posSearch').focus();
        if (BzOffline.isOnline()) loadCatalog();
    }

    function printLocal(d, uuid) {
        const w = window.open('', '_blank', 'width=380,height=640');
        if (!w) return;
        const shop = R.shop;
        const lines = d.lines.map(l => `<tr><td colspan="2">${esc(l.name)}</td></tr><tr><td>${fmtQty(BigInt(l.q))} × ${fmt(BigInt(l.price), '')}</td><td style="text-align:right">${fmt(BigInt(l.total), '')}</td></tr>`).join('');
        w.document.write(`<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Ticket provisoire</title>
            <style>body{font-family:monospace;width:72mm;margin:0 auto;font-size:12px}h1{font-size:15px;text-align:center;margin:4px 0}table{width:100%;border-collapse:collapse}.c{text-align:center}.b{font-weight:bold}.box{border:2px dashed #000;padding:4px;margin:6px 0;text-align:center;font-weight:bold}@page{margin:3mm}</style></head>
            <body><h1>${esc(shop.name)}</h1><div class="c">${esc(shop.address || '')} ${esc(shop.phone || '')}</div>
            <div class="box">VENTE PROVISOIRE — HORS LIGNE<br>en attente de validation</div>
            <div>Réf. appareil : ${uuid.slice(0, 8).toUpperCase()}<br>${new Date().toLocaleString('fr-FR')}<br>Caissier : ${esc(cfg.userName)}${d.customer ? '<br>Client : ' + esc(d.customer) : ''}</div><hr>
            <table>${lines}</table><hr>
            <div class="b">TOTAL : ${fmt(BigInt(d.total))}</div>${BigInt(d.change) > 0n ? '<div>Monnaie rendue : ' + fmt(BigInt(d.change)) + '</div>' : ''}${BigInt(d.due) > 0n ? '<div>Reste dû : ' + fmt(BigInt(d.due)) + '</div>' : ''}
            <hr><div class="c">${esc(shop.footer || '')}</div><script>window.print();<\/script></body></html>`);
        w.document.close();
    }

    // ---------- File hors ligne ----------
    const STATUS = { pending: ['En attente', 'status-warn'], synced: ['Synchronisée', 'status-ok'], conflict: ['En conflit', 'status-danger'], rejected: ['Rejetée', 'status-muted'], invalid: ['Invalide', 'status-danger'] };
    async function renderQueue() {
        const list = await BzOffline.listQueue();
        const last = await BzOffline.getMeta('last_sync_at');
        $('lastSync').textContent = last ? new Date(last).toLocaleString('fr-FR') : 'jamais';
        $('queueList').innerHTML = list.length ? list.map(e => {
            const [label, cls] = STATUS[e.status] || [e.status, 'status-muted'];
            return `<div class="border rounded-3 p-2 mb-2">
                <div class="d-flex justify-content-between"><strong>${e.sale_number ? esc(e.sale_number) : e.client_uuid.slice(0, 8).toUpperCase()}</strong><span class="status ${cls}">${label}</span></div>
                <div class="small text-muted-2">${new Date(e.created_at).toLocaleString('fr-FR')} · ${e.display && e.display.total ? fmt(BigInt(e.display.total)) : ''}</div>
                ${e.message ? `<div class="small mt-1">${esc(e.message)}</div>` : ''}
                ${(e.conflicts || []).map(c => `<div class="small text-danger">• ${esc(c.message)}</div>`).join('')}
            </div>`;
        }).join('') : '<div class="empty"><i class="bi bi-cloud-check"></i>Aucune vente hors ligne.</div>';
    }
    $('queuePanel').addEventListener('show.bs.offcanvas', renderQueue);
    $('btnSyncNow').addEventListener('click', async () => { await BzOffline.ping(); await BzOffline.syncNow(); renderQueue(); });
    document.addEventListener('bz:status', (e) => {
        const { online, counts } = e.detail;
        $('offlineBanner').classList.toggle('d-none', online);
        $('queueBadge').textContent = (counts.pending || 0) + (counts.conflict || 0);
    });

    // Raccourcis clavier
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') { e.preventDefault(); openPay(); }
        if (e.key === 'Enter' && $('doneModal').classList.contains('show')) { e.preventDefault(); doneModal.hide(); }
    });

    window.BzToast = (msg, type) => document.dispatchEvent(new CustomEvent('bz:notify', { detail: { msg, type } }));
    loadCatalog();
    renderCart();
})();
