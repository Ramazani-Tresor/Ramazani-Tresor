# UNILU-GRH — Gestion intégrée du personnel et du patrimoine universitaire

Application web Laravel destinée à l'Université de Likasi : dossier électronique unique de chaque agent (académique,
scientifique, administratif et ouvrier), carrières et promotions sur décision, diplômes et production scientifique,
structure, postes et affectations, mutations, congés et situations administratives, décès, primes, acomptes et
régularisations, soutiens sociaux, recrutement, patrimoine immobilier et mobilier, espace personnel, notifications,
tableaux de bord, éditions PDF/Excel et API.

Pile : PHP 8.3 · Laravel 12 · Livewire 3 · MySQL/MariaDB · Bootstrap 5 · Chart.js · DomPDF · OpenSpout — ressources
web servies localement. Design commun avec Biashara.

## Démarrage rapide (démonstration, données fictives)

```bash
cd unilu-grh
composer install
cp .env.example .env && php artisan key:generate && php artisan grh:backup-key
# .env : APP_ENV=local, DB_CONNECTION=sqlite, DB_DATABASE=<chemin absolu>/database/database.sqlite, GRH_DEMO=true, MAIL_MAILER=log
touch database/database.sqlite
php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder
php artisan serve
```

Comptes : `admin`, `rh`, `rh2`, `gestionnaire`, `finance`, `finance2`, `patrimoine`, `doyen.sciences`, `agent`…
mot de passe `Demo!2026grh` (détails dans `docs/GUIDE-UTILISATEUR.md`). **Ne jamais charger la démonstration en production.**

Tests : `php artisan test` → 37 tests, 378 assertions.

## Documentation

| Document | Contenu |
|---|---|
| [docs/01-BESOINS-REGLES.md](docs/01-BESOINS-REGLES.md) | Hypothèses explicites, règles de gestion et leur mise en œuvre, formules financières, matrice rôles × permissions, circuits |
| [docs/02-ARCHITECTURE.md](docs/02-ARCHITECTURE.md) | Architecture, modules, modèle de données, sécurité, versions |
| [docs/API.md](docs/API.md) | API v1 (jetons, points d'accès, périmètres) |
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installation serveur, MySQL, HTTPS, tâches planifiées, mise à jour |
| [docs/GUIDE-UTILISATEUR.md](docs/GUIDE-UTILISATEUR.md) | Guide par rôle et comptes de démonstration |
| [docs/SECURITE-SAUVEGARDES.md](docs/SECURITE-SAUVEGARDES.md) | Exploitation sécurisée, sauvegardes chiffrées, restauration, conservation |
| [docs/TESTS-ET-LIMITES.md](docs/TESTS-ET-LIMITES.md) | Résultats des tests et limites connues |
| `docs/captures/` | Captures (bureau, tablette, téléphone) |

## Commandes

| Commande | Rôle |
|---|---|
| `php artisan grh:alerts` | Alertes d'échéance (mandats, congés) et entrée en vigueur des situations |
| `php artisan grh:backup` / `grh:restore <fichier> --force` | Sauvegarde chiffrée avec copie séparée / restauration |
| `php artisan grh:backup-key` | Générer la clé de chiffrement des sauvegardes |
| `php artisan grh:audit-verify` | Vérifier l'intégrité du journal d'audit chaîné |
