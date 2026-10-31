# 03 — Charte graphique et parcours utilisateurs

## Charte

| Élément | Valeur |
|---|---|
| Couleur principale | Bleu marine `#0E1E3A` (navigation, boutons principaux, en-têtes) |
| Fond | Gris clair `#F2F4F7` ; cartes blanches |
| Accent | Turquoise `#14B8A6` (foncé `#0F8F82`) (onglets actifs, indicateurs, liens d'action secondaires) |
| États | Succès vert, avertissement ambre, erreur rouge, information bleu — toujours accompagnés d'un libellé texte (jamais la couleur seule) |
| Typographie | Inter (fichiers locaux `public/fonts`) ; chiffres tabulaires pour les montants |
| Icônes | Bootstrap Icons locales |
| Montants | Toujours affichés avec la devise (`1 250,00 USD`) ; aucune conversion implicite |

Règles d'ergonomie appliquées :

* tableaux denses mais lisibles, filtres en haut, pagination ; sur téléphone les tableaux défilent
  horizontalement dans leur carte, la page elle-même ne déborde pas (vérifié à 390 px) ;
* badges de statut cohérents (`Labels::badge`) sur tous les écrans ;
* actions irréversibles (validation, contrepassation, clôture) derrière un formulaire de
  confirmation avec motif obligatoire ;
* messages d'erreur métier en français, explicites (ex. « Le total réparti dépasse le montant du bordereau ») ;
* focus clavier visible, libellés associés aux champs, contraste AA sur les couleurs principales.

Le fichier `.claude/skills/ui-ux-pro-max/SKILL.md` cité pendant la demande n'existe pas dans le
dépôt ; la charte ci-dessus suit donc directement le cahier des charges.

## Écrans livrés

1. Connexion (+ 2FA, codes de secours, changement du mot de passe temporaire)
2. Tableau de bord selon le rôle
3. Configuration (paramètres, devises, banques et comptes)
4. Années, facultés, promotions — clôture
5. Utilisateurs, rôles et habilitations
6. Étudiants (liste filtrée, fiche, situation financière)
7. Import Excel des étudiants (modèle, correspondance, aperçu, erreurs, rapport)
8. Catégories de frais et barèmes versionnés
9. Ajustements (bourses, réductions, exonérations)
10. Saisie rapide d'un bordereau
11. Contrôle et validation des paiements
12. Relevés bancaires et rapprochement
13. Excédents et crédits
14. Contrats de paiement
15. Documents émis et vérification publique par QR
16. Contrôle financier des examens
17. Rapports, journal d'audit, synchronisation hors connexion, portail étudiant

Captures : `docs/captures/` (bureau, tablette, téléphone, hors connexion).

## Parcours principaux

### Comptable de saisie — enregistrer un bordereau (< 1 minute)

```
Année → Faculté → Promotion → liste filtrée → étudiant
  → situation (créances, soldes, tranches échues) affichée à droite
  → bordereau : banque/compte, devise, montant, date bancaire, référence
  → répartition proposée automatiquement (ordre des échéances) et modifiable
  → justificatif (PDF/JPG/PNG, contrôlé) → Soumettre
```
Contrôles immédiats : total réparti ≤ montant, devise cohérente avec le compte, compte autorisé
pour la catégorie, doublon de référence signalé comme anomalie.

### Contrôleur — contrôler, valider, rapprocher

```
File « Soumis » → ouvrir → vérifier justificatif + anomalies → Contrôlé (ou Rejet motivé)
File « Contrôlé » → Valider (affectations activées) — personne différente de la saisie
Relevés → importer → vérifier les lignes incertaines → rapprochement auto (exact) / manuel
  → confirmation bancaire des paiements → rapport de couverture
```

### Administrateur de budget — tarifer

```
Catégorie → nouveau barème (critères : faculté, cycle, programme, promotion, statut) → tranches
  → soumettre → approbation par une autre personne → génération des créances (sans doublon)
```

### Recteur / SGA — piloter

```
Tableau de bord (taux de recouvrement, encaissé validé, en attente, impayés par faculté)
  → rapports filtrés → export PDF/Excel → clôture d'année (checklist, rapport)
```

### Étudiant — consulter

```
Connexion → portail : créances, paiements, solde par devise, documents vérifiables
```
