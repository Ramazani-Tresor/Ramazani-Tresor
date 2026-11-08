# Résultats des tests et limites connues

## Exécution

```bash
php artisan test
```

Dernier résultat : **37 tests réussis, 378 assertions** (PHPUnit, SQLite en mémoire, PHP 8.3). Style : Laravel Pint.

| Domaine | Tests |
|---|---|
| Primes | prorata et absence de doublon, taux le plus spécifique et agents sans taux, acompte puis solde avec déduction automatique, séparation des tâches, contrepassation sans suppression, règles de situation (suspension 0 %, congé 100 %), décès rétroactif → trop-perçu → régularisation → arrêt des primes, paiement après décès réservé à un ayant droit |
| Mouvements | mutation externe (clôture, statut, fin des droits, dossier conservé), mutation interne (droits maintenus), date d'effet obligatoire et validation par un tiers, suspension avec durée puis réintégration, disponibilité et prolongation, décès (statut, affectation, soutien ouvert, cause réservée), notifications interne et courriel |
| Règles | matricule unique et automatique, affectation principale unique et postes pourvus, promotion sur décision validée par un tiers et historisée, mandat et alerte d'échéance, gestionnaire financier sans droit sur les grades, périmètres faculté et famille, chiffrement bancaire, journal d'audit infalsifiable |
| Modules | recrutement jusqu'à l'engagement, patrimoine et inventaire, statistiques scientifiques, soutien au deuil selon la parenté, demande de correction de l'agent |
| HTTP | parcours complet par formulaires (dossier, photo, pièces, banque, carrière, diplômes, affectation, situation, primes, soutien, correction, mutation, décès, recrutement, patrimoine, paramétrage, utilisateurs, jetons, structure, rôles), connexion/déconnexion journalisées |
| Exploitation | sauvegarde chiffrée avec copie séparée et restauration (clé erronée refusée), alertes d’échéance de mandat et de congé sans doublon, commandes planifiées |
| Écrans et sécurité | toutes les pages (43) avec données de démonstration, Livewire actif, éditions HTML/PDF/Excel et droits financiers, fiche individuelle, cloisonnement des accès (IDOR), API par jeton et périmètre, CSP sans ressource externe, mot de passe temporaire |

Vérification navigateur (Chromium/Playwright) : bureau 1366 px, tablette 820 px, téléphone 390 px — aucune erreur
JavaScript, aucun débordement horizontal ; la recherche Livewire filtre en direct ; captures dans `docs/captures/`.

## Limites connues

| Sujet | Situation |
|---|---|
| MySQL / MariaDB | Code prévu pour MySQL, mais **tests exécutés sur SQLite** ; les verrous `lockForUpdate` ne sont pas éprouvés en concurrence réelle. Recette à faire sur MySQL. |
| Courriel | Notifications par courriel implémentées et testées (canal « mail » simulé) ; **aucun envoi réel vers un serveur SMTP** n'a été effectué. |
| CSP | Livewire 3 (Alpine.js) impose `'unsafe-eval'` dans la politique des scripts ; les scripts restent locaux et les scripts en ligne sont autorisés par nonce. |
| Réglementation | Les % de primes par situation, durées de mandat, conditions de promotion et durées de conservation livrés sont des **hypothèses à valider** (voir `01-BESOINS-REGLES.md`) ; aucune condition réglementaire de promotion (ancienneté, publications…) n'est évaluée automatiquement : l'application exige la décision formelle. |
| Paie | L'application gère les **primes**, pas la paie complète (salaire de base, retenues fiscales et sociales, bulletins). |
| Cahier des charges | Le texte reçu s'arrête à la règle 9 de la section 22 ; les éventuelles règles suivantes n'ont pas été prises en compte. |
| Double authentification | Non demandée et non implémentée (possible extension) ; le mot de passe fort et la limitation des tentatives sont en place. |
| Import de masse | Pas d'import Excel des dossiers existants (reprise des données à prévoir) ; les exports Excel sont disponibles. |
| Signature électronique | Les éditions prévoient les espaces de signature ; pas de signature électronique qualifiée. |
| Windows | Installation non testée sous Windows. |
| Charge | Pas de test de performance (démonstration : 34 agents). |
