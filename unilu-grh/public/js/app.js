/* UNILU-GRH — comportements communs (aucune ressource externe). */
(function () {
    'use strict';
    // Confirmation des opérations sensibles : data-confirm sur <form> ou sur le bouton
    const modalEl = document.getElementById('confirmModal');
    let pending = null;
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.closest('[wire\\:id]') && form.hasAttribute('wire:submit')) return;
        const msg = form.dataset.confirm || (e.submitter && e.submitter.dataset.confirm);
        if (msg && !form.dataset.confirmed && modalEl) {
            e.preventDefault();
            pending = { form, submitter: e.submitter };
            document.getElementById('confirmText').textContent = msg;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        if (form.method.toLowerCase() === 'get' || form.target === '_blank') return;
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

    // Champs dépendants : data-show-when="nom=valeur1,valeur2" n'affiche le bloc que pour ces valeurs
    const toggles = document.querySelectorAll('[data-show-when]');
    const refresh = () => toggles.forEach(el => {
        const [name, values] = el.dataset.showWhen.split('=');
        const scope = el.closest('form') || document;
        const field = scope.querySelector('[name="' + name + '"]:checked') || scope.querySelector('[name="' + name + '"]');
        const show = field && values.split(',').includes(field.value);
        el.classList.toggle('d-none', !show);
        el.querySelectorAll('input,select,textarea').forEach(i => { i.disabled = !show; });
    });
    document.addEventListener('change', refresh);
    refresh();

    // Filtrage d'options par unité/famille : <select data-filter-by="nom"> et <option data-group="valeur">
    document.querySelectorAll('select[data-filter-by]').forEach(sel => {
        const scope = sel.closest('form') || document;
        const src = scope.querySelector('[name="' + sel.dataset.filterBy + '"]');
        if (!src) return;
        const sync = () => sel.querySelectorAll('option[data-group]').forEach(o => {
            const hide = src.value && o.dataset.group !== src.value;
            o.hidden = hide; o.disabled = hide;
            if (hide && o.selected) sel.value = '';
        });
        src.addEventListener('change', sync); sync();
    });

    // Envoi automatique (ex. photo) sans gestionnaire en ligne (CSP)
    document.querySelectorAll('[data-autosubmit]').forEach(i => i.addEventListener('change', () => i.form.requestSubmit ? i.form.requestSubmit() : i.form.submit()));

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    // Onglets mémorisés dans l'URL (#onglet)
    if (location.hash) {
        const t = document.querySelector('[data-bs-toggle="tab"][data-bs-target="' + location.hash + '"]');
        if (t) bootstrap.Tab.getOrCreateInstance(t).show();
    }
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(t => t.addEventListener('shown.bs.tab', () => history.replaceState(null, '', t.dataset.bsTarget)));

    // Avertissement de déconnexion automatique (la session serveur expire après la durée configurée)
    const idle = parseInt(document.body.dataset.idle || '0', 10);
    if (idle > 0) {
        let timer;
        const reset = () => { clearTimeout(timer); timer = setTimeout(() => { window.location.href = document.body.dataset.base + '/connexion?expire=1'; }, idle * 60000); };
        ['click', 'keydown', 'scroll'].forEach(ev => document.addEventListener(ev, reset, { passive: true }));
        reset();
    }

    // Graphiques : <canvas data-chart='{"type":"bar","labels":[..],"data":[..]}'>
    if (window.Chart) {
        const palette = ['#1E6FD9', '#0B1F3A', '#7FA9E3', '#12805C', '#B7791F', '#C0392B', '#5B6B84', '#9CC3F5', '#3B8BEB', '#12305A'];
        document.querySelectorAll('canvas[data-chart]').forEach(c => {
            const cfg = JSON.parse(c.dataset.chart);
            const multi = Array.isArray(cfg.series);
            const datasets = multi ? cfg.series.map((s, i) => ({ label: s.label, data: s.data, backgroundColor: palette[i % palette.length], borderColor: palette[i % palette.length], borderRadius: 6 }))
                : [{ label: cfg.label || '', data: cfg.data, backgroundColor: cfg.type === 'doughnut' ? palette : palette[0], borderColor: cfg.type === 'line' ? palette[0] : undefined, borderRadius: cfg.type === 'bar' ? 6 : 0, tension: .3 }];
            new Chart(c, {
                type: cfg.type, data: { labels: cfg.labels, datasets },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: cfg.horizontal ? 'y' : 'x',
                    plugins: { legend: { display: multi || cfg.type === 'doughnut', position: 'bottom' } },
                    scales: cfg.type === 'doughnut' ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { autoSkip: false, maxRotation: 45 } } } }
            });
        });
    }
})();
