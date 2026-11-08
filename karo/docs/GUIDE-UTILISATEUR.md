# Guide utilisateur par rôle

Principes communs à tous :

* Connexion par identifiant (ou e-mail) et mot de passe ; les rôles sensibles exigent la
  **double authentification** (application TOTP : Google Authenticator, FreeOTP, Aegis…).
  Conservez vos **codes de secours** ; en cas de perte du téléphone, lien « Mot de passe oublié ou téléphone d’authentification perdu ? » (la réinitialisation est faite par l’administrateur technique).
* Un mot de passe temporaire doit être changé à la première connexion (« Mon compte »).
* Vous ne voyez que les facultés et années de votre habilitation. Un écran refusé (403) signifie
  que l'action n'est pas dans vos droits : adressez-vous à l'administrateur technique.
* Rien n'est supprimé : une erreur se corrige par **rejet motivé**, **contrepassation** ou
  **nouvelle version**, toujours tracés dans le journal d'audit.
* Session expirée après 60 minutes d'inactivité.

---

## Comptable de saisie

**Saisir un bordereau** — menu *Saisie bordereau* :

1. Choisir l'année, la faculté, puis la promotion : la liste des étudiants se filtre. Rechercher
   par nom ou matricule.
2. Cliquer sur l'étudiant : sa situation s'affiche (créances par catégorie, échéances, solde par devise).
3. Renseigner le bordereau : compte bancaire bénéficiaire (la devise suit le compte), montant,
   **date bancaire** du bordereau, référence bancaire, nom du déposant.
4. Vérifier la répartition proposée (échéances les plus anciennes d'abord) ; la modifier si le
   bordereau indique un autre usage. Le total réparti ne peut pas dépasser le montant ; un reste
   non réparti devient un excédent traité plus tard.
5. Joindre le justificatif (PDF, JPG ou PNG) et **Soumettre**. Le paiement reçoit un numéro
   `PAY-AAAA-NNNNNN`, statut *Soumis*.

Les anomalies (référence déjà vue, montant inhabituel, date future, compte non prévu pour la
catégorie…) sont signalées au contrôleur ; elles n'empêchent pas toujours la soumission.

**Hors connexion** : voir `SYNCHRONISATION.md`. Vos saisies restent *en attente* jusqu'au retour du réseau.

**Autres tâches** : créer/modifier une fiche étudiant, lancer un import Excel (si habilité),
préparer un contrat de paiement, consulter les paiements de votre périmètre (*Paiements*).

Vous ne pouvez pas contrôler ni valider vos propres saisies.

## Contrôleur / validateur

**Contrôle** — menu *Contrôle* :

1. Onglet *Soumis* : ouvrir le paiement, comparer justificatif et données, lire les anomalies.
2. *Marquer contrôlé* ou *Rejeter* (motif obligatoire, visible par le comptable).
3. Onglet *Contrôlés* : *Valider* — les affectations deviennent actives et réduisent les soldes.
   Selon le paramétrage, la personne qui valide doit être différente de celle qui a contrôlé.
4. Un paiement validé erroné se **contrepasse** (motif obligatoire) : les affectations sont
   annulées, le paiement reste visible avec le statut *Contrepassé*.

**Relevés bancaires** — menu *Banque* :

1. Importer le relevé du compte (XLSX, CSV, ou PDF texte). Les lignes déjà importées (relevés qui
   se chevauchent) sont ignorées automatiquement.
2. Vérifier les **lignes incertaines** (PDF, montants ou dates douteux) : corriger ou confirmer.
3. *Rapprochement automatique* : seules les correspondances exactes et uniques sont rapprochées.
4. Pour chaque ligne restante, choisir parmi les candidats proposés (score référence/montant/date),
   rapprocher plusieurs paiements à une ligne ou une ligne partielle, ou *Écarter* la ligne (motif obligatoire).
5. Les paiements rapprochés passent en *Confirmé banque*. Le **rapport de couverture** liste
   paiements non confirmés et lignes non rapprochées.

**Excédents** — menu *Excédents* : compenser sur une autre créance de la même devise, rembourser
(référence de sortie), reporter sur l'année suivante ou conserver en crédit.

## Administrateur de budget

* **Catégories de frais** (*Frais*) : nature, déclencheur (inscription, session d'examen, manuel),
  statuts concernés (nouveaux/anciens), finalistes uniquement, comptes bancaires autorisés.
* **Barèmes** : *Nouveau barème* → catégorie, année, critères (faculté, cycle, programme, promotion,
  statut), devise, montant, tranches (la somme doit égaler le montant). Soumettre ; **une autre
  personne** habilitée approuve. Un changement de tarif crée une **nouvelle version** ; choisir
  s'il s'applique aux créances existantes (ajustement tracé) ou seulement aux nouvelles.
* **Génération des créances** : depuis le barème approuvé ou à l'inscription ; sans doublon.
* **Ajustements** (*Ajustements*) : bourse, réduction, exonération — proposés avec justificatif,
  approuvés par un tiers.
* **Taux de change** : saisie et approbation ; aucune compensation entre devises sans taux approuvé.
* **Contrats** : approbation, suivi des échéances, archivage du contrat signé.
* **Examens** : seuils et dates limites par session ; dérogations motivées.

## Recteur et Secrétaire général académique

* **Tableau de bord** : encaissé validé, en attente, confirmé banque, taux de recouvrement,
  impayés par faculté et promotion, alertes. Chaque indicateur affiche sa définition (icône d’information).
* **Rapports** : recouvrement, soldes, paiements, affectations, retards, crédits, ajustements —
  filtres année/faculté/promotion/période, export PDF ou Excel (mêmes droits que l'écran).
* **Clôture d'année** (*Années*) : consulter la liste de contrôle (paiements en attente, lignes non
  rapprochées…), clôturer avec motif ; arriérés et crédits sont reportés sans doublon. Une
  réouverture exceptionnelle exige un motif et est tracée.
* Le SGA suit en particulier les listes d'**autorisation aux examens** (*Examens*).

## Administrateur technique

* **Utilisateurs** : créer, attribuer des rôles avec périmètre (faculté, année), réinitialiser le
  mot de passe (temporaire), réinitialiser la 2FA, désactiver/archiver. Le système refuse de
  donner des approbations financières à un administrateur technique.
* **Rôles** : consulter la matrice des permissions ; modifier les permissions d'un rôle (les
  cumuls interdits sont bloqués).
* **Paramètres** : identité de l'établissement, signataires, séparation des tâches, justificatif
  obligatoire, tolérance de rapprochement, hors connexion, portail étudiant.
* **Sauvegardes** : voir `SAUVEGARDE-RESTAURATION.md`.
* **Synchronisation** : surveiller les appareils et les conflits.

## Auditeur

* Accès en **lecture** aux paiements, rapports, documents et au **journal d'audit** (*Audit*) :
  filtres par utilisateur, action, objet, période ; export CSV.
* Indicateur d'intégrité du journal (chaîne de hachage) ; toute altération est signalée.
* Aucun droit de saisie, de validation ou de configuration.

## Étudiant

* *Mon espace* : créances de l'année, échéances, paiements et leur statut (soumis, validé,
  confirmé banque), solde par devise, documents émis.
* Chaque document officiel porte un **QR code** : sa lecture ouvre une page publique de
  vérification affichant uniquement le numéro, le type, la date, le statut (valide/annulé) et le
  nom masqué — aucun montant détaillé.

---

## Comptes de démonstration (base de démonstration uniquement)

Mot de passe commun : `Demo!2026karo`. Les rôles sensibles demandent un code TOTP généré à
partir de la clé de démonstration `KARODEMOKAROD2FA` (à saisir dans l'application
d'authentification). Ces valeurs sont publiques et **ne doivent jamais exister en production**.

| Identifiant | Rôle |
|---|---|
| `admin` | Administrateur technique |
| `budget`, `budget2` | Administrateurs du budget (deux personnes pour l'approbation croisée) |
| `comptable` | Comptable de saisie (toutes facultés) |
| `comptable.droit` | Comptable limité à la Faculté de Droit (fictive) |
| `controleur` | Contrôleur / validateur |
| `recteur` | Recteur |
| `sga` | Secrétaire général académique |
| `auditeur` | Auditeur |
| `etudiant` | Étudiante (portail) |
