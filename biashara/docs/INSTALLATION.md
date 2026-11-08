# Installation et exploitation sous Windows / XAMPP

## 1. Prérequis

| Élément | Version | Remarque |
|---|---|---|
| Windows | 10 ou 11 (64 bits) | |
| XAMPP | 8.2.x (PHP **8.2.12** ou supérieur, MariaDB 10.4+) | <https://www.apachefriends.org> |
| Composer | 2.x | <https://getcomposer.org/Composer-Setup.exe> (choisir `C:\xampp\php\php.exe`) |
| Navigateur | Chrome / Edge / Firefox récents ; Android : Chrome | IndexedDB et Service Worker requis pour le hors ligne |

Node.js **n'est pas nécessaire** : les bibliothèques front (Bootstrap, icônes, Chart.js) sont fournies dans `public/vendor`.

### Extensions PHP à activer

Dans `C:\xampp\php\php.ini`, vérifiez que ces lignes **ne commencent pas** par `;` :

```
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```

`ctype`, `curl`, `dom`, `filter`, `json`, `tokenizer`, `xml`, `session` sont actives par défaut. **`bcmath` n'est pas requise.**
Redémarrez Apache après modification. Vérification : `php -m` dans un terminal.

## 2. Installation

```powershell
# 1. Copier le dossier « biashara » du dépôt dans C:\xampp\htdocs\biashara
#    (git clone https://github.com/Ramazani-Tresor/Ramazani-Tresor.git puis copie du sous-dossier)
cd C:\xampp\htdocs\biashara

# 2. Dépendances PHP (versions exactes figées par composer.lock)
composer install --no-dev --optimize-autoloader

# 3. Configuration
copy .env.example .env
php artisan key:generate
```

4. Démarrez **Apache** et **MySQL** depuis le panneau XAMPP, ouvrez <http://localhost/phpmyadmin> et créez la base `biashara`, interclassement **utf8mb4_unicode_ci**.
5. Éditez `.env` : `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`, et choisissez `INITIAL_ADMIN_PASSWORD`.

```powershell
# 6. Tables + données indispensables (rôles, permissions, modes de paiement, unités, compte admin)
php artisan migrate --seed --force

# 7. Lien public pour les photos d'articles et le logo
php artisan storage:link

# 8. (Facultatif) données de DÉMONSTRATION fictives — ne pas faire en production
php artisan db:seed --class=DemoSeeder

# 9. Optimisation (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3. Lancement

**Option A — serveur intégré (simple, un poste) :**
```powershell
php artisan serve --host=0.0.0.0 --port=8000
```
Accès : <http://localhost:8000> (ou `http://IP-du-poste:8000` depuis une tablette du réseau local).

**Option B — Apache XAMPP (recommandé, démarrage automatique)** : créez un hôte virtuel dans `C:\xampp\apache\conf\extra\httpd-vhosts.conf` :

```apache
<VirtualHost *:80>
    ServerName biashara.local
    DocumentRoot "C:/xampp/htdocs/biashara/public"
    <Directory "C:/xampp/htdocs/biashara/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Ajoutez `127.0.0.1 biashara.local` à `C:\Windows\System32\drivers\etc\hosts`, mettez `APP_URL=http://biashara.local`, redémarrez Apache.
L'accès direct `http://localhost/biashara/public` fonctionne aussi (mettre `APP_URL` en conséquence).

Première connexion : identifiant **`admin`**, mot de passe = `INITIAL_ADMIN_PASSWORD` (ou `admin12345` si vide) — **changez-le immédiatement** (menu utilisateur → Mon compte). Puis : *Paramètres* (nom, logo, devise, taux, taxes), *Utilisateurs*, *Catégories*, *Produits* (ou import).

## 4. Accès à distance et HTTPS

La consultation à distance et le mode hors ligne sur tablette nécessitent **HTTPS** (les navigateurs n'activent le Service Worker qu'en HTTPS ou sur `localhost`).

- **Hébergement** : déployez l'application sur un serveur (VPS, hébergement PHP/MySQL) avec un certificat (Let's Encrypt), `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.
- **Poste local exposé** : un tunnel sécurisé (ex. Cloudflare Tunnel) ou un reverse-proxy HTTPS ; n'ouvrez jamais le port MySQL vers Internet.
- **Réseau local uniquement** : certificat local (ex. `mkcert`) installé sur chaque appareil, ou utilisation du point de vente sur le poste serveur lui-même (`localhost`).

## 5. Tests

```powershell
composer install          # avec les dépendances de développement
php artisan test          # 45 tests, base SQLite en mémoire (extension pdo_sqlite requise)
php artisan app:check-stock   # contrôle de cohérence stock / mouvements sur la base réelle
```

## 6. Sauvegarde et restauration

**Sauvegarde**
- Depuis l'application : *Paramètres → Sauvegardes → Créer une sauvegarde* (fichier `storage/app/backups/sauvegarde_AAAAMMJJ_HHMMSS.sql`).
- En ligne de commande : `php artisan app:backup`.
- Automatique : Planificateur de tâches Windows → action `C:\xampp\php\php.exe`, arguments `artisan app:backup`, dossier de départ `C:\xampp\htdocs\biashara`, tous les jours à 22 h.
- Sauvegardez aussi `storage/app/private/expenses` (justificatifs), `storage/app/public` (photos, logo) et le fichier `.env` (dans un lieu sûr : il contient la clé de chiffrement).
- Conservez des copies **hors du poste** (clé USB chiffrée, stockage en ligne).

**Restauration**
1. Arrêtez l'utilisation de l'application (fermez les caisses).
2. phpMyAdmin → base `biashara` → *Importer* le fichier `.sql`, ou :
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root -p biashara < storage\app\backups\sauvegarde_AAAAMMJJ_HHMMSS.sql
   ```
3. Restaurez les dossiers `storage/app/private/expenses` et `storage/app/public`, et le même `APP_KEY` dans `.env`.
4. `php artisan optimize:clear` puis `php artisan app:check-stock`.
5. Testez la restauration une fois par trimestre sur un poste de test.

## 7. Mise à jour

```powershell
php artisan down
git pull                    # ou remplacement des fichiers
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```
Faites toujours une sauvegarde avant une mise à jour.

## 8. Dépannage

| Symptôme | Cause probable |
|---|---|
| Page blanche / erreur 500 | `storage` et `bootstrap/cache` non accessibles en écriture ; consulter `storage/logs/laravel.log` |
| « could not find driver » | `extension=pdo_mysql` désactivée |
| Photos ou logo absents | `php artisan storage:link` non exécuté |
| PDF illisible / caractères | extension `gd` ou `mbstring` manquante |
| Hors ligne inopérant sur tablette | accès en HTTP non local : HTTPS requis |
| « Session expirée » | délai `SESSION_LIFETIME` dépassé ; se reconnecter |
