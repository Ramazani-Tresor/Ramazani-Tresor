# Guide d'installation locale (serveur du réseau de l'université)

Karo fonctionne **entièrement sur le réseau local** : aucune ressource Internet n'est nécessaire
après l'installation (les dépendances PHP sont dans `vendor/`, les ressources web dans `public/`).

> Statut de vérification : l'installation a été exécutée et testée sous **Linux avec PHP 8.3 et
> SQLite**. Les étapes MySQL et Windows ci-dessous sont documentées mais **n'ont pas été exécutées**
> dans l'environnement de développement ; faites une recette sur votre serveur.

## 1. Prérequis

| Élément | Minimum |
|---|---|
| Serveur | 2 cœurs, 4 Go RAM, 50 Go disque (justificatifs + sauvegardes), onduleur recommandé |
| Système | Linux (Ubuntu 22.04/24.04, Debian 12) ou Windows Server/10/11 |
| PHP | 8.2 ou 8.3 avec extensions : `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `fileinfo`, `intl`, `bcmath`, `xml`, `curl` |
| Base | MySQL 8.0+ ou MariaDB 10.6+ (`mysqldump` et `mysql` disponibles pour les sauvegardes) |
| Composer | 2.x (uniquement si `vendor/` n'est pas fourni) |
| Proxy HTTPS | Caddy, Nginx ou Apache (HTTPS obligatoire sur le LAN pour la PWA et les cookies sécurisés) |

## 2. Base de données (MySQL)

```sql
CREATE DATABASE karo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'karo'@'localhost' IDENTIFIED BY '<mot-de-passe-fort>';
GRANT ALL PRIVILEGES ON karo.* TO 'karo'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Application

```bash
cd /srv/karo                      # (Windows : C:\karo)
composer install --no-dev --optimize-autoloader   # si vendor/ absent
cp .env.example .env              # (Windows : copy .env.example .env)
php artisan key:generate
php artisan karo:backup-key       # génère BACKUP_ENCRYPTION_KEY dans .env
```

Éditer `.env` :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://karo.univ.local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=karo
DB_USERNAME=karo
DB_PASSWORD=<mot-de-passe-fort>
SESSION_SECURE_COOKIE=true
BACKUP_PATH=/srv/karo-backups
BACKUP_SECONDARY_PATH=/mnt/disque-externe/karo   # autre disque ou partage réseau
INITIAL_ADMIN_PASSWORD=                         # vide = mot de passe généré et affiché une seule fois
KARO_DEMO=false
```

**La clé `BACKUP_ENCRYPTION_KEY` doit être copiée hors du serveur** (coffre, enveloppe scellée) :
sans elle, aucune sauvegarde ne peut être restaurée.

```bash
php artisan migrate --force
php artisan db:seed --force       # rôles, permissions, devises, catégories (sans montants), compte admin
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Le compte `admin` créé doit changer son mot de passe à la première connexion et configurer la
double authentification. Il s'agit d'un **administrateur technique** : il ne peut pas recevoir
de rôle d'approbation financière (règle de séparation des tâches).

Droits des dossiers (Linux) :

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 750 storage bootstrap/cache
```

## 4. HTTPS sur le réseau local

Exemple avec **Caddy** et un certificat interne (autorité locale de Caddy, à installer sur les postes) :

```
karo.univ.local {
    tls internal
    root * /srv/karo/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
    request_body { max_size 20MB }
}
```

Alternative : `mkcert` pour créer une autorité locale et un certificat, puis Nginx/Apache.
Déclarer `karo.univ.local` dans le DNS local ou dans le fichier `hosts` des postes.
Si un proxy est placé devant PHP, renseigner `TRUSTED_PROXIES` dans `.env`.

## 5. Tâches de fond

* **File d'attente** (imports volumineux) :
  `php artisan queue:work --tries=1` en service (systemd sous Linux, NSSM/Planificateur sous Windows).
  Pour un petit site, `QUEUE_CONNECTION=sync` traite les imports immédiatement.
* **Sauvegarde quotidienne** — crontab :
  ```
  30 22 * * *  cd /srv/karo && php artisan karo:backup >> storage/logs/backup.log 2>&1
  0  23 * * 0  cd /srv/karo && php artisan karo:audit-verify >> storage/logs/audit.log 2>&1
  ```
  Sous Windows : Planificateur de tâches exécutant `php artisan karo:backup` dans `C:\karo`.

## 6. Données de démonstration (poste de formation uniquement)

Jamais sur la base de production :

```bash
# .env : KARO_DEMO=true, APP_ENV=local, base séparée (ex. DB_DATABASE=karo_demo ou SQLite)
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Le seeder refuse de s'exécuter si `KARO_DEMO` n'est pas `true` ou si `APP_ENV=production`.
Comptes de démonstration : voir `README.md`.

## 7. Démarrage rapide de test (SQLite, sans serveur web)

```bash
cp .env.example .env && php artisan key:generate
# .env : DB_CONNECTION=sqlite (DB_DATABASE=chemin absolu de database/database.sqlite), KARO_DEMO=true
touch database/database.sqlite
php artisan migrate --seed && php artisan db:seed --class=DemoSeeder
php artisan serve --host=0.0.0.0 --port=8000
```

## 8. Mise à jour

```bash
php artisan karo:backup           # toujours sauvegarder avant
php artisan down
# remplacer le code (git pull ou copie)
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```
