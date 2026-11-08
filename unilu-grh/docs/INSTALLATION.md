# Guide d'installation

> Vérifié dans l'environnement de développement : Linux, PHP 8.3, SQLite (tests et démonstration). Les étapes MySQL,
> SMTP et HTTPS sont documentées mais n'ont pas été exécutées ici : faites une recette sur le serveur cible.

## 1. Prérequis

| Élément | Minimum |
|---|---|
| Serveur | Linux (Ubuntu 22.04/24.04, Debian 12) ou Windows Server ; 2 cœurs, 4 Go RAM, 50 Go disque |
| PHP | 8.2 ou 8.3 avec `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `fileinfo`, `intl`, `bcmath`, `xml`, `curl` |
| Base | MySQL 8 ou MariaDB 10.6+ (`mysqldump` et `mysql` pour les sauvegardes) |
| Composer | 2.x |
| Web | Nginx, Apache ou Caddy avec HTTPS |
| Courriel | Serveur SMTP (facultatif : sinon notifications internes uniquement) |

## 2. Base de données

```sql
CREATE DATABASE unilu_grh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'unilu_grh'@'localhost' IDENTIFIED BY '<mot-de-passe-fort>';
GRANT ALL PRIVILEGES ON unilu_grh.* TO 'unilu_grh'@'localhost';
```

## 3. Application

```bash
cd /srv/unilu-grh
composer install --no-dev --optimize-autoloader     # exécute aussi la découverte des paquets (Livewire)
cp .env.example .env
php artisan key:generate
php artisan grh:backup-key                          # clé des sauvegardes : en garder une copie HORS du serveur
```

Renseigner `.env` : `APP_URL`, `DB_*`, `MAIL_*`, `BACKUP_PATH`, `BACKUP_SECONDARY_PATH` (autre disque ou serveur),
`GRH_MATRICULE_PREFIX`, `SESSION_LIFETIME` (minutes d'inactivité avant déconnexion, 30 par défaut).

```bash
php artisan migrate --force
php artisan db:seed --force          # rôles, permissions, référentiels sans montants, compte « admin » (mot de passe affiché une fois)
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache && chmod -R 750 storage bootstrap/cache
```

Première connexion avec `admin` : changer le mot de passe, puis dans **Paramétrage** compléter l'institution
(en-têtes, signataires), les années académiques, la structure (facultés, départements, services, postes), les grades,
mandats, types de situations (vérifier les % de primes), **taux de primes** et montants des aides au deuil.
Créer ensuite les comptes et leurs rôles avec périmètre.

## 4. Serveur web (exemple Nginx)

```nginx
server {
    listen 443 ssl http2;
    server_name grh.unilu.local;
    ssl_certificate     /etc/ssl/unilu/grh.crt;
    ssl_certificate_key /etc/ssl/unilu/grh.key;
    root /srv/unilu-grh/public;
    index index.php;
    client_max_body_size 12M;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.3-fpm.sock; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; }
    location ~ /\.(?!well-known) { deny all; }
}
```

Le dossier `storage/app/private` n'est jamais servi : les pièces passent par l'application, avec contrôle d'accès.

## 5. Tâches planifiées et file d'attente

```cron
* * * * * cd /srv/unilu-grh && php artisan schedule:run >> /dev/null 2>&1
```

Le planificateur exécute : `grh:alerts` (06 h 15 : échéances de mandats et de congés, entrée en vigueur des
situations), `grh:backup` (22 h 30), `grh:audit-verify` (dimanche 23 h). Si `QUEUE_CONNECTION=database`, lancer un
worker permanent : `php artisan queue:work --tries=3` (systemd ou superviseur).

## 6. Démonstration (base séparée uniquement)

```bash
cp .env.example .env && php artisan key:generate
# .env : APP_ENV=local, DB_CONNECTION=sqlite, DB_DATABASE=<chemin absolu>/database/database.sqlite, GRH_DEMO=true, MAIL_MAILER=log
touch database/database.sqlite
php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder
php artisan serve
```

Le seeder refuse de s'exécuter si `GRH_DEMO` n'est pas `true` ou en production. Comptes : voir `README.md`.

## 7. Mise à jour

```bash
php artisan grh:backup && php artisan down
git pull   # ou copie du code
composer install --no-dev --optimize-autoloader
php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```
