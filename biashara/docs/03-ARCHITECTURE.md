# 3. Architecture technique

## 3.1 Vue d'ensemble

```
Navigateur (PC, tablette, smartphone, terminal Android)
 ├─ Pages Blade + Bootstrap 5 (rendu serveur)
 ├─ Point de vente : public/js/pos.js (calculs BigInt, panier, paiements)
 ├─ public/js/offline-store.js : IndexedDB (catalogue, file de ventes) + synchronisation
 └─ Service Worker (/sw.js) : cache des ressources statiques et de la page du point de vente
            │ HTTPS, session + jeton CSRF, JSON
            ▼
Laravel 12 (PHP 8.2+)
 ├─ routes/web.php            routes + middleware auth / can:permission / throttle
 ├─ Http/Controllers          présentation : validation des requêtes, réponses HTML/JSON
 ├─ Services                  logique métier transactionnelle (une opération = une transaction)
 ├─ Models (Eloquent)         accès aux données, relations, conversions
 ├─ Support                   Money (décimal exact), Settings, PermissionRegistry
 └─ Exceptions/BusinessException  règle de gestion violée → message français (HTTP 422 en JSON)
            │
            ▼
MySQL / MariaDB (InnoDB, utf8mb4) — transactions, verrous de ligne, contraintes d'unicité
```

## 3.2 Séparation des responsabilités

| Couche | Rôle | Exemples |
|---|---|---|
| Présentation | Vues Blade, JavaScript, formatage | `resources/views/**`, `public/js/*.js` |
| Validation | Règles de forme (types, bornes, existence) | `$request->validate(...)` dans les contrôleurs, `PosController::saleRules()` |
| Autorisation | Permissions vérifiées serveur | `can:` dans `routes/web.php`, `Gate::before` (AppServiceProvider), contrôles de propriété (ventes, caisses, dépenses) |
| Logique métier | Règles de gestion, transactions, verrous | `app/Services/*.php` |
| Données | Modèles, migrations, contraintes | `app/Models`, `database/migrations` |

Les contrôleurs ne contiennent aucune règle de gestion : ils valident l'entrée, appellent un service et mettent en forme la réponse. Les services sont testés directement (`tests/Feature`).

## 3.3 Arborescence

```
app/
  Exceptions/BusinessException.php
  Http/Controllers/   Auth, Dashboard, Remote, Category, Product, Supplier, Purchase, Stock, Pos, Sale,
                      Return, Customer, Cash, Expense, Inventory, Report, Sync, User, Role, Setting, Audit, Pwa
  Http/Middleware/    EnsureUserIsActive, SecurityHeaders
  Models/             30 modèles
  Services/           Sale, Return, Settlement, Stock, Cash, Purchase, Expense, Inventory, Sync, Product,
                      Numbering, FinancialMetrics, Report, Backup, Audit
  Support/            Money, Settings, PermissionRegistry, DbErrors
  helpers.php         money(), qty(), setting(), can_do(), report_cell()
database/migrations/  schéma complet (12 fichiers, dont 2 techniques Laravel : cache, jobs)
database/seeders/     RolePermission, ReferenceData (indispensables), DemoSeeder (fictif)
resources/views/      écrans Blade, reçus (receipts/), layouts
resources/pwa/sw.js   Service Worker
public/css/app.css    identité visuelle
public/js/            app.js, offline-store.js, pos.js
public/vendor/        Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Chart.js 4.4.4 (servis localement : aucun CDN requis)
tests/                Feature (règles métier, permissions, hors ligne, écrans) et Unit
docs/                 cette documentation
```

## 3.4 Sécurité

| Menace / exigence | Mesure |
|---|---|
| Mots de passe | Hachage bcrypt (cast `hashed`), politique ≥ 8 caractères avec lettres et chiffres |
| Force brute | `throttle:10,1` sur la connexion, échecs journalisés |
| Injection SQL | Requêtes Eloquent/Query Builder paramétrées ; aucune concaténation de saisie utilisateur dans du SQL |
| XSS | Échappement Blade `{{ }}` ; fonction `esc()` dans le JavaScript ; `{!! !!}` réservé à du HTML statique |
| CSRF | Jeton sur tous les formulaires et en-tête `X-CSRF-TOKEN` pour les appels JSON (rafraîchi automatiquement après une période hors ligne) |
| Permissions | Contrôle serveur sur chaque route et contrôle de propriété (un caissier ne voit que ses ventes / sa caisse) |
| Comptes désactivés | Déconnexion immédiate (middleware `EnsureUserIsActive`) |
| Intégrité | Transactions, verrous de ligne, contraintes d'unicité, clés étrangères `RESTRICT` (aucune suppression d'historique) |
| Justificatifs | Stockés sur le disque privé `storage/app/private/expenses`, téléchargés uniquement via un contrôleur qui vérifie les droits |
| Exports et sauvegardes | Permissions dédiées, journalisés ; sauvegardes dans `storage/app/backups` (non exposé), noms de fichiers validés |
| Secrets | `.env` (exclu de Git) ; `.env.example` sans secret ; mot de passe MySQL transmis à `mysqldump` par variable d'environnement |
| En-têtes | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` |
| Sessions | Chiffrées, durée configurable, cookie `Secure` en HTTPS |
| Audit | `audit_logs` : qui, quoi, quand, adresse IP, valeurs avant/après |

## 3.5 Concurrence

- Vente : verrou sur la session de caisse, puis sur les articles (ordre croissant d'id), contrôle du stock **après** verrou. Le second caissier attend la fin de la première transaction puis voit le stock à jour → message « Stock insuffisant ».
- Numérotation : verrou sur la ligne de `document_sequences`.
- Retours / règlements / annulations : verrou sur la vente (et ses lignes) avant contrôle des quantités et montants.
- Ouverture de caisse : contrainte unique `open_lock` (deux clics simultanés → une seule session).
- Les tests automatisés s'exécutent sur SQLite (verrouillage global en écriture) ; le comportement de verrouillage de ligne est celui de MySQL InnoDB en production.

## 3.6 PWA et hors ligne

- `GET /manifest.webmanifest` (démarrage sur le point de vente), icônes 192/512 px.
- `GET /sw.js` : pré-cache des ressources, stratégie « cache d'abord » pour les statiques, « réseau d'abord » pour les pages avec repli sur la dernière page du point de vente puis une page « hors ligne ». Les API JSON ne sont jamais mises en cache.
- IndexedDB `biashara` : magasins `catalog`, `meta`, `queue`.
- `POST /api/sync/ventes` (lot de 50 ventes max) et `POST /api/sync/etat` (état des conflits) ; `GET /api/ping` détecte un serveur injoignable même si le Wi-Fi est connecté.
- Un Service Worker exige HTTPS, sauf sur `localhost`. Pour des tablettes connectées au poste XAMPP par le réseau local, voir `INSTALLATION.md` (HTTPS local).
