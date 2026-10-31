# Fonctionnement hors connexion et synchronisation

## 1. Modèle retenu

Karo est d'abord une application **serveur sur le réseau local** : tant que le poste atteint le
serveur du campus, tout fonctionne même sans Internet. Le mode « hors connexion » couvre le cas où
le poste **perd le réseau local** (tablette dans un bâtiment éloigné, coupure Wi-Fi).

| Fonction | Hors connexion ? |
|---|---|
| Consultation de la liste des étudiants du périmètre et de leurs soldes (instantané) | Oui, lecture seule, avec date de l'instantané affichée |
| Préparation de bordereaux (saisie) | Oui, mis en file locale, statut « en attente » |
| Justificatif joint à une saisie hors connexion | Oui (stocké dans la file locale, taille limitée) |
| Contrôle, validation, rapprochement, ajustements, barèmes, clôture, documents officiels | **Non** — exigent le serveur |
| Encaissement « validé » hors connexion | **Jamais** : une saisie synchronisée arrive au statut *Soumis* et suit le circuit normal |

## 2. Composants

* `resources/pwa/sw.js` (servi à `/sw.js`) : met en cache l'interface (CSS, JS, polices, page
  `/hors-connexion`) ; aucune donnée financière n'est mise en cache par le service worker.
* `public/js/offline.js` : base **IndexedDB** propre à chaque utilisateur (`karo-{id}`), magasins
  `meta` (instantané, identifiant d'appareil) et `queue` (saisies).
* API : `GET /api/ping`, `GET /api/csrf`, `GET /api/hors-connexion/instantane`,
  `POST /api/sync/bordereaux` (limitation de débit dédiée).
* Écran **Synchronisation** (`/synchronisation`) : file locale du poste, état de chaque saisie,
  conflits côté serveur à arbitrer.

## 3. Règles de synchronisation

1. Chaque saisie reçoit à sa création un **identifiant unique** (`client_uuid`) généré sur le poste.
2. La file est **persistante** (IndexedDB) : fermeture du navigateur ou redémarrage sans perte.
3. Au retour du réseau, la file est envoyée automatiquement (et via le bouton « Synchroniser »).
4. Le serveur **revérifie tout** au moment de la réception : compte actif, permissions
   `payments.create` et `sync.use` **actuelles**, périmètre faculté, année non clôturée, compte
   bancaire autorisé, devise, montants, justificatif obligatoire selon paramètre.
5. **Idempotence** : un `client_uuid` déjà reçu renvoie le résultat initial (« Déjà enregistrée,
   aucun doublon ») ; renvoyer la file autant de fois que nécessaire est sans risque.
6. Résultats possibles par saisie :
   * **acceptée** → paiement créé au statut *Soumis* (canal `offline_sync`) ;
   * **rejetée** → motif explicite (droits révoqués, année clôturée, étudiant inconnu…) ; rien n'est créé ;
   * **conflit** → par exemple référence bancaire déjà utilisée : rien n'est créé automatiquement,
     un responsable (permission `sync.resolve`) décide « accepter quand même » ou « rejeter » avec motif.
     Le conflit n'est jamais résolu silencieusement.
7. Chaque réception, acceptation, rejet et arbitrage est tracé (`sync_operations` + journal d'audit).

## 4. Limites et précautions

* **Durée de vie de l'instantané** : `offline_ttl_hours` (72 h par défaut). Passé ce délai, il est
  effacé et la consultation hors connexion n'est plus possible. Les soldes affichés hors connexion
  peuvent être dépassés : ils sont étiquetés avec leur date.
* **Taille** : `offline_max_payload_kb` (2 Mo par défaut) par saisie, justificatif compris.
* **Révocation des droits** : un poste isolé ne peut pas apprendre qu'un compte a été désactivé. Il
  peut continuer à *préparer* des saisies jusqu'à l'expiration de l'instantané, mais elles seront
  **rejetées à la synchronisation**. L'instantané est effacé à la déconnexion.
* **Données sur l'appareil** : IndexedDB n'est pas chiffrée par Karo. N'utiliser que des appareils
  de l'université, avec verrouillage de session et chiffrement du disque activés. Le contenu de
  l'instantané est limité au périmètre de l'utilisateur (matricules, noms, soldes).
* **Effacement du navigateur** : vider les données du site détruit la file non synchronisée.
  L'écran Synchronisation affiche le nombre de saisies en attente ; synchroniser avant toute
  maintenance du poste.
* PWA et service worker exigent **HTTPS** (ou `localhost`).
* Désactivation globale possible : paramètre `offline_enabled`.

## 5. Vérification effectuée

Testé automatiquement (`test_reprise_de_synchronisation_sans_double_paiement`,
`test_conflit_explicite_et_droits_reverifies`) et manuellement dans Chromium (Playwright) :
saisie mise en file hors ligne → « en attente » ; retour réseau → « acceptée » (paiement
`PAY-2026-000021`, statut *Soumis*, canal `offline_sync`) ; renvoi du même identifiant →
« Déjà enregistrée (aucun doublon) ».
