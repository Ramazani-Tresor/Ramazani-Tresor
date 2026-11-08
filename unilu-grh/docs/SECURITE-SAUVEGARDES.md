# Sécurité, sauvegardes et conservation

## Mesures de sécurité

Voir le tableau détaillé dans `02-ARCHITECTURE.md` §3. Points d'exploitation :

- Servir l'application **uniquement en HTTPS** (`SESSION_SECURE_COOKIE=true`) ; renseigner `TRUSTED_PROXIES` derrière un proxy.
- `APP_DEBUG=false` en production ; journaux dans `storage/logs`.
- Les secrets (`APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`, `BACKUP_ENCRYPTION_KEY`) restent dans `.env`, jamais dans le code.
  **Perdre `APP_KEY` rend illisibles les données chiffrées** (coordonnées bancaires, causes de décès) : la conserver
  avec la clé de sauvegarde, hors du serveur.
- Vérifier chaque semaine l'intégrité du journal : `php artisan grh:audit-verify`.

## Sauvegardes

`php artisan grh:backup` (planifié chaque soir) crée `grh_AAAAMMJJ_HHMMSS.kbk` : vidage de la base
(`mysqldump --single-transaction`) + pièces numérisées et photos (`storage/app/private`), compressés puis chiffrés en
**AES-256-GCM** (toute altération est détectée). Une copie est écrite dans `BACKUP_SECONDARY_PATH`. Les fichiers de
l'emplacement principal plus anciens que `BACKUP_KEEP_DAYS` (30 par défaut) sont purgés ; la copie séparée n'est
jamais purgée par l'application.

**Restauration** :

```bash
php artisan down
php artisan grh:restore /chemin/grh_20261108_223000.kbk --force
php artisan grh:audit-verify
php artisan up
```

**Test de restauration mensuel** sur un serveur de test (même `APP_KEY` et clé de sauvegarde) : restaurer, vérifier
l'intégrité du journal, le nombre d'agents, l'ouverture d'une pièce et un état des primes ; consigner le résultat.

Objectifs proposés (à valider) : **RPO 24 h** (sauvegarde quotidienne, à rapprocher en période de paie) ;
**RTO 4 h** sur un serveur de secours préparé.

## Conservation des archives

- Dossiers du personnel, décisions, affectations, situations, droits et paiements : **jamais supprimés** (y compris
  après décès, mutation externe ou retraite) ; les comptes utilisateurs se désactivent.
- Années académiques : jamais supprimées ; le changement d'année ne touche pas l'historique.
- Journal d'audit et historique des modifications : conservés intégralement.
- Sauvegardes : 30 jours sur l'emplacement principal ; conserver hors site au moins une sauvegarde mensuelle et une par
  année académique clôturée (durées légales à confirmer par le service juridique).
