# Karo — Gestion des frais académiques et connexes

Application web **locale** (réseau du campus, sans dépendance Internet) pour gérer les frais d'une
université : étudiants et import Excel, catégories de frais et barèmes versionnés, créances,
bourses et exonérations, saisie rapide des bordereaux bancaires, contrôle et validation séparés,
import des relevés et rapprochement, excédents et crédits, contrats de paiement, documents
vérifiables par QR code, contrôle financier des examens, clôture d'année, tableaux de bord et
rapports, journal d'audit chaîné, sauvegardes chiffrées et saisie hors connexion synchronisée.

Pile : Laravel 12, PHP 8.2+, MySQL 8 (SQLite pour les tests et la démo), Blade + Bootstrap locaux, PWA.

## Démarrage rapide (démonstration, SQLite)

```bash
cd karo
cp .env.example .env && php artisan key:generate && php artisan karo:backup-key
# Dans .env : DB_CONNECTION=sqlite, DB_DATABASE=<chemin absolu>/database/database.sqlite, KARO_DEMO=true, APP_ENV=local
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder
php artisan serve --port=8000
```

Ouvrir http://127.0.0.1:8000 — comptes fictifs : `comptable`, `controleur`, `budget`, `recteur`,
`auditeur`, `admin`, `etudiant`… mot de passe `Demo!2026karo`, clé TOTP de démonstration
`KARODEMOKAROD2FA` (voir `docs/GUIDE-UTILISATEUR.md`). **Ne jamais charger la démo en production.**

Tests : `php artisan test` → 44 tests, 620 assertions.

## Documentation

| Document | Contenu |
|---|---|
| [docs/01-BESOINS-ROLES-REGLES.md](docs/01-BESOINS-ROLES-REGLES.md) | Synthèse des besoins, hypothèses, matrice rôles × permissions, circuits, règles et formules financières |
| [docs/02-ARCHITECTURE-MODELE.md](docs/02-ARCHITECTURE-MODELE.md) | Architecture, modèles conceptuel et logique, séparation des notions, versions |
| [docs/03-CHARTE-ET-PARCOURS.md](docs/03-CHARTE-ET-PARCOURS.md) | Charte graphique, écrans, parcours utilisateurs |
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installation locale (Linux/Windows, MySQL, HTTPS LAN, tâches planifiées) |
| [docs/GUIDE-UTILISATEUR.md](docs/GUIDE-UTILISATEUR.md) | Guide par rôle |
| [docs/SAUVEGARDE-RESTAURATION.md](docs/SAUVEGARDE-RESTAURATION.md) | Sauvegardes chiffrées, restauration, test, RTO/RPO, conservation |
| [docs/SYNCHRONISATION.md](docs/SYNCHRONISATION.md) | Hors connexion, file locale, idempotence, conflits, limites |
| [docs/TESTS-ET-LIMITES.md](docs/TESTS-ET-LIMITES.md) | Résultats des tests, correspondance avec les critères, limites connues |
| `docs/modeles/` | Modèles Excel : import des étudiants, relevé bancaire |
| `docs/captures/` | Captures d'écran (bureau, tablette, téléphone, hors connexion) |

## Commandes utiles

| Commande | Rôle |
|---|---|
| `php artisan karo:backup-key` | Générer la clé de chiffrement des sauvegardes |
| `php artisan karo:backup` | Sauvegarde chiffrée (base + justificatifs) avec copie séparée |
| `php artisan karo:restore <fichier> --force` | Restaurer une sauvegarde |
| `php artisan karo:audit-verify` | Vérifier l'intégrité du journal d'audit |
