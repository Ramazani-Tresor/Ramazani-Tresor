# Dépendances et versions exactes

Les versions exactes de **toutes** les dépendances PHP (y compris transitives) sont figées dans `composer.lock` ; `composer install` les reproduit à l'identique. La plateforme est fixée à PHP 8.2.12 (`config.platform.php` dans `composer.json`) pour garantir la compatibilité avec XAMPP.

## Serveur

| Composant | Version | Rôle |
|---|---|---|
| PHP | ≥ 8.2.12 | moteur |
| MySQL / MariaDB | MySQL 8.0+ / MariaDB 10.4+ (XAMPP) | base de données InnoDB, utf8mb4 |
| laravel/framework | 12.70.1 | framework MVC |
| barryvdh/laravel-dompdf | 3.1.2 (dompdf/dompdf 3.1.6) | export PDF (reçus, rapports) |
| openspout/openspout | 4.28.5 | import Excel/CSV, export Excel (.xlsx) |
| brick/math | 0.14.8 (dépendance de Laravel) | calculs décimaux exacts |
| laravel/tinker | 2.11.1 | console |

## Développement (non installées avec `--no-dev`)

| Composant | Version |
|---|---|
| phpunit/phpunit | 11.5.57 |
| laravel/pint | 1.30.4 |
| fakerphp/faker | 1.24.1 |
| mockery/mockery | 1.6.15 |
| nunomaduro/collision | 8.9.5 |
| laravel/pail | 1.2.7 |

## Navigateur (fichiers fournis dans `public/vendor`, licences incluses)

| Bibliothèque | Version | Licence |
|---|---|---|
| Bootstrap | 5.3.3 | MIT |
| Bootstrap Icons | 1.11.3 | MIT |
| Chart.js | 4.4.4 | MIT |

Aucune ressource n'est chargée depuis un CDN : l'application fonctionne sur un réseau local sans Internet.
