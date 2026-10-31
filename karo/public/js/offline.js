/*
 * Karo — fonctionnement hors connexion (poste déconnecté du serveur de référence).
 *  - Instantané LIMITÉ (périmètre de l'utilisateur, champs minimaux) stocké dans IndexedDB, avec date d'expiration :
 *    il est effacé automatiquement à l'expiration et à la déconnexion.
 *  - File persistante des saisies préparées (déclarations de bordereaux), conservée jusqu'à l'acquittement du serveur.
 *  - Reprise : au retour du réseau, à l'ouverture de l'application, et sur action manuelle.
 *  - Accessibilité réelle du serveur vérifiée par /api/ping (pas seulement navigator.onLine).
 *  - Chaque saisie porte un UUID (clé d'idempotence) : renvoyée plusieurs fois, elle n'est enregistrée qu'une fois.
 * Aucune validation financière n'a lieu sur le poste : tout est provisoire jusqu'à l'acceptation par le serveur.
 */
(function () {
    'use strict';
    const base = document.body.dataset.base || '';
    const userId = document.body.dataset.user || 'anon';
    const DB = 'karo-' + userId;
    let online = navigator.onLine;
    let syncing = false;
    let dbp = null;

    function db() {
        if (dbp) return dbp;
        dbp = new Promise((res, rej) => {
            const r = indexedDB.open(DB, 1);
            r.onupgradeneeded = () => {
                const d = r.result;
                d.createObjectStore('meta', { keyPath: 'key' });
                d.createObjectStore('queue', { keyPath: 'client_uuid' });
            };
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
        return dbp;
    }
    const p = (r) => new Promise((res, rej) => { r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error); });
    async function store(name, mode) { return (await db()).transaction(name, mode).objectStore(name); }
    async function getMeta(key) { const r = await p((await store('meta', 'readonly')).get(key)); return r ? r.value : null; }
    async function setMeta(key, value) { return p((await store('meta', 'readwrite')).put({ key, value })); }
    async function list() { return p((await store('queue', 'readonly')).getAll()); }
    async function put(entry) { return p((await store('queue', 'readwrite')).put(entry)); }

    function uuid() {
        if (crypto.randomUUID) return crypto.randomUUID();
        const b = crypto.getRandomValues(new Uint8Array(16)); b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
        const h = [...b].map(x => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }
    function deviceId() {
        try { let id = localStorage.getItem('karo_device'); if (!id) { id = 'poste-' + uuid(); localStorage.setItem('karo_device', id); } return id; } catch (e) { return 'poste-inconnu'; }
    }
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    async function refreshCsrf() {
        try { const r = await fetch(base + '/api/csrf', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } }); if (!r.ok) return false; document.querySelector('meta[name="csrf-token"]').content = (await r.json()).token; return true; } catch (e) { return false; }
    }
    async function api(path, body, retry = true) {
        const r = await fetch(base + path, { method: body ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Device-Id': deviceId() }, body: body ? JSON.stringify(body) : undefined });
        if (r.status === 419 && retry && await refreshCsrf()) return api(path, body, false);
        let data = null; try { data = await r.json(); } catch (e) {}
        return { ok: r.ok, status: r.status, data };
    }

    async function ping() {
        try {
            const c = new AbortController(); const t = setTimeout(() => c.abort(), 5000);
            const r = await fetch(base + '/api/ping', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: c.signal });
            clearTimeout(t); setOnline(r.ok);
        } catch (e) { setOnline(false); }
        return online;
    }
    function setOnline(v) { const changed = v !== online; online = v; if (changed) { emit(); if (v) sync(); } }

    async function emit() {
        let queued = 0;
        try { queued = (await list()).filter(e => e.status === 'pending').length; } catch (e) {}
        document.dispatchEvent(new CustomEvent('karo:status', { detail: { online, queued } }));
    }

    async function snapshot() {
        const s = await getMeta('snapshot');
        if (s && Date.parse(s.expires_at) < Date.now()) { await setMeta('snapshot', null); return null; }   // expiration
        return s;
    }
    async function refreshSnapshot() {
        if (!online) return snapshot();
        const r = await api('/api/hors-connexion/instantane');
        if (r.ok && r.data) { await setMeta('snapshot', r.data); return r.data; }
        return snapshot();
    }

    async function enqueue(payload, display) {
        const entry = { client_uuid: payload.client_uuid, payload, display, status: 'pending', messages: [], created_at: new Date().toISOString() };
        await put(entry); emit(); sync();
        return entry;
    }

    async function sync() {
        if (syncing || !online) return;
        syncing = true;
        try {
            const pendingEntries = (await list()).filter(e => e.status === 'pending');
            for (let i = 0; i < pendingEntries.length; i += 10) {
                const batch = pendingEntries.slice(i, i + 10);
                const r = await api('/api/sync/bordereaux', { device_id: deviceId(), operations: batch.map(e => e.payload) });
                if (!r.ok || !r.data) break;                    // conservé dans la file jusqu'à acquittement
                for (const res of r.data.results) {
                    const e = batch.find(x => x.client_uuid === res.client_uuid);
                    if (e) { e.status = res.status; e.messages = res.messages; e.payment_number = res.payment_number; e.synced_at = new Date().toISOString(); delete e.payload.proof_base64; await put(e); }
                }
                await setMeta('last_sync', new Date().toISOString());
            }
        } catch (e) { /* réessai ultérieur */ } finally { syncing = false; emit(); }
    }

    window.addEventListener('online', ping);
    window.addEventListener('offline', () => setOnline(false));
    setInterval(ping, 30000);
    document.addEventListener('DOMContentLoaded', () => { ping().then(() => sync()); emit(); snapshot(); });
    // Déconnexion : l'instantané de consultation est effacé ; la file des saisies non acquittées est conservée
    // (sinon des bordereaux préparés seraient perdus) et reste liée à ce compte.
    document.addEventListener('submit', (e) => { if (e.target.action && e.target.action.endsWith('/deconnexion')) { setMeta('snapshot', null).catch(() => {}); } }, true);

    window.KaroOffline = { uuid, deviceId, ping, sync, enqueue, list, snapshot, refreshSnapshot, getMeta, isOnline: () => online };
})();
