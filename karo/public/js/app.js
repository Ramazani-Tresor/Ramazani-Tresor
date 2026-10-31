/* Karo — comportements communs. */
(function () {
    'use strict';
    // Confirmation des opérations sensibles : data-confirm sur <form> ou sur le bouton
    const modalEl = document.getElementById('confirmModal');
    let pending = null;
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const msg = form.dataset.confirm || (e.submitter && e.submitter.dataset.confirm);
        if (msg && !form.dataset.confirmed && modalEl) {
            e.preventDefault();
            pending = { form, submitter: e.submitter };
            document.getElementById('confirmText').textContent = msg;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        if (form.method.toLowerCase() === 'get') return;
        if (form.dataset.submitting) { e.preventDefault(); return; }   // anti double soumission
        form.dataset.submitting = '1';
        document.body.classList.add('is-loading');
        setTimeout(() => form.querySelectorAll('button[type=submit], button:not([type])').forEach(b => { b.disabled = true; }), 0);
    });
    document.getElementById('confirmOk')?.addEventListener('click', () => {
        if (!pending) return;
        const { form, submitter } = pending;
        form.dataset.confirmed = '1';
        if (submitter && submitter.name) { const h = document.createElement('input'); h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value; form.appendChild(h); }
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });
    window.addEventListener('pageshow', () => document.body.classList.remove('is-loading'));

    // Identifiants d'idempotence générés côté poste
    document.querySelectorAll('input[data-uuid]').forEach(i => { if (!i.value) i.value = window.KaroOffline ? KaroOffline.uuid() : ''; });

    // Cascade faculté → filière / promotion
    document.querySelectorAll('[data-cascade]').forEach(form => {
        const fac = form.querySelector('[data-faculty]');
        if (!fac) return;
        const sync = () => form.querySelectorAll('option[data-fac]').forEach(o => {
            const hide = fac.value && o.dataset.fac !== fac.value;
            o.hidden = hide; o.disabled = hide;
            if (hide && o.selected) o.parentElement.value = '';
        });
        fac.addEventListener('change', sync); sync();
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    // Indicateur d'accessibilité du serveur et file hors connexion
    document.addEventListener('karo:status', (e) => {
        const { online, queued } = e.detail;
        const pill = document.getElementById('netPill');
        if (pill) { pill.className = 'net-pill ' + (online ? 'net-on' : 'net-off'); pill.querySelector('.t').textContent = online ? 'Serveur joignable' : 'Serveur injoignable'; }
        const q = document.getElementById('queuePill');
        if (q) { q.classList.toggle('d-none', !queued); q.querySelector('.t').textContent = queued + ' en attente'; }
    });

    if ('serviceWorker' in navigator && document.body.dataset.sw) {
        window.addEventListener('load', () => navigator.serviceWorker.register(document.body.dataset.sw, { scope: (document.body.dataset.base || '') + '/' }).catch(() => {}));
    }
})();
