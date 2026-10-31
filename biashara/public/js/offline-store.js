/*
 * Biashara — stockage hors ligne (IndexedDB) et moteur de synchronisation.
 *
 * - catalog : articles synchronisés pour consulter et vendre sans réseau ;
 * - queue   : ventes enregistrées sur l'appareil, identifiées par un UUID généré localement.
 *             États : pending (en attente) · synced (synchronisée) · conflict (en conflit) · rejected (rejetée) · invalid.
 * Une vente n'est jamais modifiée localement après coup : en cas de conflit, c'est le serveur
 * (un responsable) qui décide. Le serveur ignore toute vente dont l'UUID a déjà été traité.
 */
(function () {
    'use strict';
    const DB_NAME = 'biashara';
    const DB_VERSION = 1;
    const base = document.body.dataset.base || '';
    let dbPromise = null;
    let online = navigator.onLine;
    let syncing = false;

    function openDb() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains('catalog')) db.createObjectStore('catalog', { keyPath: 'id' });
                if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta', { keyPath: 'key' });
                if (!db.objectStoreNames.contains('queue')) {
                    const q = db.createObjectStore('queue', { keyPath: 'client_uuid' });
                    q.createIndex('status', 'status');
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
        return dbPromise;
    }

    function tx(store, mode, fn) {
        return openDb().then(db => new Promise((resolve, reject) => {
            const t = db.transaction(store, mode);
            const s = t.objectStore(store);
            let result;
            Promise.resolve(fn(s)).then(r => { result = r; });
            t.oncomplete = () => resolve(result);
            t.onerror = () => reject(t.error);
            t.onabort = () => reject(t.error);
        }));
    }

    function reqP(r) { return new Promise((res, rej) => { r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error); }); }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        const b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        const h = [...b].map(x => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    function deviceId() {
        try {
            let id = localStorage.getItem('bz_device_id');
            if (!id) { id = 'dev-' + uuid(); localStorage.setItem('bz_device_id', id); }
            return id;
        } catch (e) { return 'dev-ephemere'; }
    }

    function csrf() { const m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }

    /** Jeton CSRF à jour (la page du point de vente peut provenir du cache hors ligne). */
    async function refreshCsrf() {
        try {
            const r = await fetch(base + '/api/csrf', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!r.ok) return false;
            const d = await r.json();
            document.querySelector('meta[name="csrf-token"]').content = d.token;
            return true;
        } catch (e) { return false; }
    }

    async function api(path, body, method = 'POST', retried = false) {
        const res = await fetch(base + path, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (res.status === 419 && !retried && await refreshCsrf()) return api(path, body, method, true);
        let data = null;
        try { data = await res.json(); } catch (e) { /* réponse non JSON */ }
        return { ok: res.ok, status: res.status, data };
    }

    async function saveCatalog(products, generatedAt) {
        await tx('catalog', 'readwrite', s => { s.clear(); products.forEach(p => s.put(p)); });
        await tx('meta', 'readwrite', s => s.put({ key: 'catalog_at', value: generatedAt || new Date().toISOString() }));
    }
    async function getCatalog() { return tx('catalog', 'readonly', s => reqP(s.getAll())); }
    async function getMeta(key) { const r = await tx('meta', 'readonly', s => reqP(s.get(key))); return r ? r.value : null; }
    async function setMeta(key, value) { return tx('meta', 'readwrite', s => s.put({ key, value })); }

    async function queueSale(payload, display) {
        const entry = {
            client_uuid: payload.client_uuid, payload, display: display || {},
            user_id: Number(document.body.dataset.userId), status: 'pending',
            created_at: new Date().toISOString(), message: null, conflicts: [], sale_number: null,
        };
        await tx('queue', 'readwrite', s => s.put(entry));
        emit();
        return entry;
    }
    async function listQueue() {
        const all = await tx('queue', 'readonly', s => reqP(s.getAll()));
        return all.sort((a, b) => (a.created_at < b.created_at ? 1 : -1));
    }
    async function updateEntry(uuid, patch) {
        return tx('queue', 'readwrite', s => reqP(s.get(uuid)).then(e => { if (e) s.put(Object.assign(e, patch)); }));
    }
    async function purgeSynced(days = 7) {
        const limit = Date.now() - days * 86400000;
        const all = await listQueue();
        await tx('queue', 'readwrite', s => all.filter(e => e.status === 'synced' && Date.parse(e.created_at) < limit).forEach(e => s.delete(e.client_uuid)));
    }
    async function counts() {
        const all = await listQueue();
        const c = { pending: 0, synced: 0, conflict: 0, rejected: 0, invalid: 0 };
        all.forEach(e => { c[e.status] = (c[e.status] || 0) + 1; });
        return c;
    }

    /** Envoie les ventes en attente (par lots) puis rafraîchit l'état des conflits. */
    async function syncNow() {
        if (syncing || !online) return null;
        syncing = true;
        try {
            const me = Number(document.body.dataset.userId);
            const all = await listQueue();
            const pending = all.filter(e => e.status === 'pending' && e.user_id === me).reverse();
            for (let i = 0; i < pending.length; i += 20) {
                const batch = pending.slice(i, i + 20);
                const r = await api('/api/sync/ventes', { device_id: deviceId(), sales: batch.map(e => e.payload) });
                if (r.status === 401 || r.status === 419) { notify('Session expirée : reconnectez-vous pour synchroniser.', 'warning'); break; }
                if (!r.ok || !r.data) break;
                for (const res of r.data.results) {
                    await updateEntry(res.client_uuid, {
                        status: res.status, message: res.message || null, conflicts: res.conflicts || [],
                        sale_number: res.sale_number || null, synced_at: new Date().toISOString(),
                    });
                }
                await setMeta('last_sync_at', new Date().toISOString());
            }
            // Conflits résolus par un responsable entre-temps ?
            const waiting = (await listQueue()).filter(e => e.status === 'conflict').map(e => e.client_uuid);
            if (waiting.length) {
                const r = await api('/api/sync/etat', { uuids: waiting.slice(0, 200), device_id: deviceId() });
                if (r.ok && r.data) {
                    for (const [u, st] of Object.entries(r.data.statuses)) {
                        if (st.status && st.status !== 'unknown') await updateEntry(u, { status: st.status, message: st.message || null, sale_number: st.sale_number || null });
                    }
                }
            }
            await purgeSynced();
            return await counts();
        } catch (e) {
            return null;
        } finally {
            syncing = false;
            emit();
        }
    }

    async function ping() {
        try {
            const ctrl = new AbortController();
            const t = setTimeout(() => ctrl.abort(), 5000);
            const res = await fetch(base + '/api/ping', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: ctrl.signal });
            clearTimeout(t);
            setOnline(res.ok || res.status === 401);
        } catch (e) { setOnline(false); }
    }

    function setOnline(v) {
        const changed = v !== online;
        online = v;
        if (changed) { emit(); if (v) syncNow(); }
    }

    async function emit() {
        let c = { pending: 0, conflict: 0 };
        try { c = await counts(); } catch (e) { /* IndexedDB indisponible */ }
        const lastSync = await getMeta('last_sync_at').catch(() => null);
        document.dispatchEvent(new CustomEvent('bz:status', { detail: { online, counts: c, lastSync } }));
    }

    function notify(msg, type = 'info') {
        document.dispatchEvent(new CustomEvent('bz:notify', { detail: { msg, type } }));
    }

    window.addEventListener('online', () => ping());
    window.addEventListener('offline', () => setOnline(false));
    setInterval(() => { ping(); }, 30000);
    setInterval(() => { if (online) syncNow(); }, 60000);
    document.addEventListener('DOMContentLoaded', () => { emit(); ping().then(() => { if (online) syncNow(); }); });

    window.BzOffline = { uuid, deviceId, api, saveCatalog, getCatalog, getMeta, setMeta, queueSale, listQueue, syncNow, counts, isOnline: () => online, setOnline, ping, emit };
})();
