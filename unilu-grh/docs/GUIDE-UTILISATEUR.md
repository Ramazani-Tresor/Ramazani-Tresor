# Guide utilisateur par rôle

**Principes communs.** Connexion par identifiant ou adresse électronique ; déconnexion automatique après 30 minutes
d'inactivité. Vous ne voyez que les agents de votre périmètre. Une décision (promotion, mutation, situation, décès,
paiement) saisie par une personne est **validée par une autre**. Rien n'est supprimé : on rejette, on clôture, on
contrepasse ou on régularise. Toutes les opérations sont journalisées.

## Gestionnaire du personnel

- **Agents › Nouveau dossier** : identité, coordonnées, situation familiale, classification, affectation initiale. Le
  matricule est attribué automatiquement si le champ est vide.
- **Fiche agent › Actions** :
  - *Nomination / promotion* : type, nouveau grade, catégorie ou mandat, date d'effet, et décision obligatoire
    (numéro, date, autorité signataire, pièce numérisée). Sans effet avant validation.
  - *Nouvelle affectation* : structure, poste, fonction, date, motif ; l'affectation principale précédente est clôturée.
  - *Congé / situation* : type (le % de primes est indiqué), dates, motif, décision.
  - *Mutation* : interne (structure d'accueil) ou externe (institution d'accueil), date d'effet, décision.
  - *Diplôme / thèse*, *Pièce numérisée*, *Enregistrer un décès*.
- **Recrutement** : enregistrer les candidatures, joindre les pièces, passer en examen.
- **Demandes de correction** : accepter (les rubriques d'identité sont mises à jour automatiquement) ou rejeter, avec
  une réponse notifiée à l'agent.

## Administrateur RH

Tout ce que fait le gestionnaire, plus :
- **Valider ou rejeter** les décisions de carrière (*Décisions de carrière*), mutations, situations (*Congés et
  situations* : approuver, clôturer et réintégrer, voir les échéances) et décès (*Décès*).
- Valider les candidatures et **prononcer l'engagement** (matricule, classification, affectation initiale).
- Approuver les **soutiens sociaux**.
- **Paramétrage** : années académiques, grades, catégories, mandats, fonctions, types de congés et règles financières,
  types de primes, degrés de parenté, types de pièces, structure et postes.

## Responsables du personnel académique / scientifique

Gestion des dossiers, carrières, diplômes et situations limitée à leur famille de personnel.

## Gestionnaire financier

- **Taux de primes** : montant par type, avec critères (famille, catégorie, grade, fonction, service, année) et
  validité. Pour changer un montant, clôturer l'ancien taux et en créer un nouveau.
- **Générer une période** : type de prime, dates, libellé (ex. `2026-11`). La génération peut être relancée sans
  créer de doublon ; les agents sans taux sont signalés.
- **Primes › détail d'un droit** : saisir un acompte, le paiement du solde (montant proposé = solde restant, acomptes
  déduits) ou une régularisation de trop-perçu ; ajuster le droit avec un motif.
- **Paiements et acomptes** : valider ou rejeter les mouvements saisis par un collègue ; contrepasser un mouvement
  validé erroné ; suivre les acomptes non régularisés.
- **Soutiens sociaux** : enregistrer le paiement des soutiens approuvés.
- **Coordonnées bancaires** (onglet *Primes et soutiens* de la fiche) : données chiffrées.
- Après un **décès**, le paiement des arriérés exige de désigner l'ayant droit.

## Responsable du patrimoine

- **Biens › Entrée en patrimoine** : catégorie, code (automatique si vide), désignation, localisation (ou bien
  contenant), valeur, état, affectation, responsable.
- Fiche du bien : **affectation / transfert**, **suivi de l'état**, **réforme** ou **mise au rebut** (PV obligatoire).
  L'historique des mouvements est conservé.
- **Inventaires** : ouvrir une campagne (par unité), constater présence, état et localisation, puis clôturer : les
  biens sont mis à jour et chaque constat est historisé ; les biens non retrouvés sont signalés.

## Responsable de faculté / de service

Consultation des agents de son unité (et sous-unités), tableau de bord limité, enregistrement des demandes de congé.

## Rectorat et administration (tableau de bord)

**Tableau de bord** filtrable par année académique, faculté ou service, famille, catégorie et situation : effectifs
(par famille, grade, catégorie, faculté, service), docteurs, DEA, thèses par année, promotions, mutations, départs,
décès, suspensions, agents en congé et, pour les profils financiers, primes prévues et payées, acomptes, arriérés et
soutiens. **Statistiques scientifiques** : DEA, mémoires, thèses par année, par faculté et par département, docteurs
parmi le personnel scientifique et parmi les candidats à la promotion. **Éditions** : listes et états en PDF (en-tête
institutionnel, référence, date, signatures) ou Excel.

## Agent du personnel (espace personnel)

*Mon espace* : dossier administratif, grade, carrière, diplômes, affectations, primes prévues et payées, acomptes,
soldes, soutiens, congés et situation ; téléchargement des documents autorisés et de la fiche individuelle ;
**demandes de correction** avec suivi ; **notifications** (cloche et courriel).

## Administrateur technique (super administrateur)

**Utilisateurs** (création avec mot de passe temporaire, rôles et périmètres, désactivation), **Rôles et
permissions**, **Journal d'audit** (connexions, opérations, contrôle d'intégrité), **Sauvegardes**, **Jetons d'API**.

## Comptes de démonstration (base de démonstration uniquement)

Mot de passe commun : `Demo!2026grh`. Toutes les données sont fictives (matricules `DEMO-…`).

| Identifiant | Rôle |
|---|---|
| `admin` | Super administrateur |
| `rh`, `rh2` | Administrateurs RH (deux personnes pour les validations croisées) |
| `gestionnaire` | Gestionnaire du personnel |
| `acad` / `scient` | Responsables du personnel académique / scientifique |
| `finance`, `finance2` | Gestionnaires financiers (saisie / validation) |
| `patrimoine` | Responsable du patrimoine |
| `doyen.sciences` | Responsable de la Faculté des Sciences |
| `chef.personnel` | Responsable du service du personnel |
| `agent` | Agent (espace personnel) |
