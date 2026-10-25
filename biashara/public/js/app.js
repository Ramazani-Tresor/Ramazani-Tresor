/* Biashara — comportements communs à toutes les pages. */
(function () {
    'use strict';

    // Indicateur de connexion et de synchronisation
    document.addEventListener('bz:status', (e) => {
        const { online, counts } = e.detail;
        const pill = document.getElementById('connPill');
        if (pill) {
            pill.className = 'conn-pill ' + (online ? 'conn-online' : 'conn-offline');
            pill.querySelector('.txt').textContent = online ? 'En ligne' : 'Hors ligne';
        }
        const sync = document.getElementById('syncPill');
        if (sync) {
            const waiting = (counts.pending || 0) + (counts.conflict || 0);
            sync.classList.toggle('d-none', waiting === 0);
            sync.querySelector('.txt').textContent = (counts.pending || 0) + ' en attente' + (counts.conflict ? ' · ' + counts.conflict + ' conflit(s)' : '');
        }
    });

    // Confirmation des opérations sensibles : <form data-confirm="..."> ou <button data-confirm="...">
    const modalEl = document.getElementById('confirmModal');
    let pendingForm = null;
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const msg = form.dataset.confirm || (e.submitter && e.submitter.dataset.confirm);
        if (msg && !form.dataset.confirmed && modalEl) {
            e.preventDefault();
            pendingForm = { form, submitter: e.submitter };
            document.getElementById('confirmMessage').textContent = msg;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        // Anti double soumission : on désactive les boutons pendant l'envoi.
        if (!form.hasAttribute('data-allow-resubmit')) {
            if (form.dataset.submitting) { e.preventDefault(); return; }
            form.dataset.submitting = '1';
            setTimeout(() => {
                form.querySelectorAll('button[type=submit], button:not([type])').forEach(b => {
                    b.disabled = true;
                    if (b === e.submitter) b.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1"></span>');
                });
            }, 0);
        }
    });
    document.getElementById('confirmOk')?.addEventListener('click', () => {
        if (!pendingForm) return;
        const { form, submitter } = pendingForm;
        form.dataset.confirmed = '1';
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        if (submitter && submitter.name) {
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value;
            form.appendChild(h);
        }
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });

    // Champs « identifiant de transaction » générés côté appareil (idempotence)
    document.querySelectorAll('input[data-uuid]').forEach(i => { if (!i.value) i.value = window.BzOffline ? BzOffline.uuid() : ''; });

    // Notifications légères
    document.addEventListener('bz:notify', (e) => {
        const { msg, type } = e.detail;
        const box = document.createElement('div');
        box.className = `alert alert-${type === 'error' ? 'danger' : type} shadow position-fixed bottom-0 end-0 m-3`;
        box.style.zIndex = 2000; box.style.maxWidth = '420px';
        box.textContent = msg;
        document.body.appendChild(box);
        setTimeout(() => box.remove(), 6000);
    });

    // Service Worker (PWA)
    if ('serviceWorker' in navigator && document.body.dataset.sw) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(document.body.dataset.sw, { scope: (document.body.dataset.base || '') + '/' }).catch(() => {});
        });
    }

    // Activation des info-bulles
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
})();
