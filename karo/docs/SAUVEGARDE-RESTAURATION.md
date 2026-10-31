# Sauvegarde, restauration et conservation

## 1. Ce qui est sauvegardé

`php artisan karo:backup` (ou bouton « Sauvegarder » dans Paramètres, permission `backups.manage`) produit
un fichier `karo_AAAAMMJJ_HHMMSS.kbk` contenant :

* le vidage complet de la base (`mysqldump --single-transaction` en MySQL ; copie du fichier en SQLite) ;
* tous les fichiers privés (`storage/app/private` : justificatifs, relevés importés, contrats signés,
  pièces d'étudiants) ;
* un `manifest.json` (date, moteur de base).

Le tout est compressé (zip) puis **chiffré en AES-256-GCM** (en-tête `KARO1`, IV aléatoire, tag
d'authenticité) avec `BACKUP_ENCRYPTION_KEY`. Toute modification du fichier est détectée au déchiffrement.
Une **copie identique** est écrite dans `BACKUP_SECONDARY_PATH` (autre disque, NAS, disque amovible).
L'opération est inscrite dans le journal d'audit ; la commande affiche l'empreinte SHA-256.

`.env` (secrets) n'est **pas** inclus : le conserver séparément, avec la clé de sauvegarde, dans un coffre.

## 2. Procédure quotidienne recommandée

| Quand | Action | Responsable |
|---|---|---|
| Chaque soir (22 h 30, cron) | `karo:backup` automatique | serveur |
| Chaque matin | Vérifier `storage/logs/backup.log` et la présence du fichier du jour sur les 2 emplacements | administrateur technique |
| Chaque semaine | Emporter une copie hors site (disque rotatif dans un autre bâtiment / coffre) | administrateur technique |
| Chaque semaine | `karo:audit-verify` | administrateur technique / auditeur |
| Chaque mois | **Test de restauration** sur un poste de test (voir §4) | administrateur technique + auditeur |
| Avant clôture d'année ou mise à jour | Sauvegarde manuelle supplémentaire | administrateur technique |

## 3. Restauration

```bash
php artisan down
php artisan karo:restore /srv/karo-backups/karo_20261030_223000.kbk --force
php artisan karo:audit-verify
php artisan up
```

La restauration remplace la base **et** les fichiers privés. Elle échoue proprement si la clé est
incorrecte ou si le fichier est altéré. En MySQL, elle utilise le client `mysql`
(`BACKUP_MYSQL` si le binaire n'est pas dans le `PATH`).

Après restauration : informer les utilisateurs que les saisies postérieures à la sauvegarde doivent
être ressaisies ; les postes hors connexion dont la file contient encore des bordereaux les
renverront (idempotence par identifiant unique : pas de doublon).

## 4. Test de restauration (mensuel)

1. Copier le dernier `.kbk` sur un poste de test disposant de Karo et de la même clé.
2. `php artisan karo:restore <fichier> --force`.
3. Vérifier : `karo:audit-verify` OK ; nombre d'étudiants et de paiements cohérent avec la production ;
   ouverture d'un justificatif ; rapport de recouvrement identique à celui du jour de la sauvegarde.
4. Consigner date, fichier, durée et résultat dans le registre d'exploitation.

Un test automatisé (`test_sauvegarde_chiffree_et_restauration`) vérifie création, chiffrement,
copie séparée, rejet d'une clé erronée et restauration effective (sur SQLite).

## 5. Objectifs RTO / RPO

| Objectif | Valeur proposée | Hypothèse |
|---|---|---|
| **RPO** (perte de données maximale) | 24 h (sauvegarde nocturne) ; ramenable à 4 h en planifiant `karo:backup` toutes les 4 h en période d'inscription | volume modéré (< 2 Go de justificatifs ajoutés par jour) |
| **RTO** (délai de remise en service) | 4 h sur serveur de secours préparé ; 1 jour ouvré si le matériel doit être remplacé | PHP/MySQL préinstallés sur la machine de secours, clé disponible |

Ces valeurs sont des propositions à valider par la direction ; elles dépendent du matériel et de
l'organisation (personne d'astreinte, disque hors site accessible).

## 6. Conservation et archivage

* Sauvegardes : conserver les 14 dernières quotidiennes, 12 mensuelles (première du mois) et une
  par année académique clôturée (à garder au moins 10 ans, à adapter à la réglementation applicable).
  **La purge des anciennes sauvegardes n'est pas automatisée** dans cette version : l'effectuer par
  script système (ex. `find /srv/karo-backups -name 'karo_*.kbk' -mtime +14`) après avoir isolé les
  mensuelles et annuelles.
* Données en base : aucune suppression des écritures financières ; les années clôturées restent
  consultables. Les comptes utilisateurs sont **archivés** (désactivés), jamais supprimés, pour
  préserver la traçabilité.
* Journal d'audit : non modifiable, conservé intégralement ; exportable (CSV) par l'auditeur.
* Justificatifs : conservés avec la base ; pas de purge automatique.
