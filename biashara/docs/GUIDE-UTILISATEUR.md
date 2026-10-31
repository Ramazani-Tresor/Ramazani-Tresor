# Guide utilisateur

## Se connecter
Saisissez votre **identifiant** (ou e-mail) et votre **mot de passe**. Après connexion, l'**accueil** affiche les tuiles auxquelles votre rôle donne accès. En haut à droite : l'indicateur **En ligne / Hors ligne** et, s'il y en a, le nombre de ventes **en attente de synchronisation**.
Changer son mot de passe : menu utilisateur (en haut à droite) → *Mon compte*.

---

## Caissier

### 1. Ouvrir sa caisse (chaque jour)
*Caisse* → comptez les espèces du tiroir → saisissez le **fonds initial** → *Ouvrir la caisse*. Sans caisse ouverte, aucune vente n'est possible.

### 2. Vendre
*Ventes* (point de vente) :
1. **Scanner** le code-barres (le lecteur agit comme un clavier : l'article est ajouté automatiquement), ou **taper** un nom/une référence puis Entrée, ou **toucher** un article. Les boutons de catégorie filtrent la liste.
2. Ajuster les quantités avec **− / +** ou en saisissant la valeur (décimales autorisées pour les articles au poids/au litre).
3. Client : *Client de passage* par défaut ; pour un client connu, tapez son nom ou téléphone ; bouton 👤+ pour en créer un.
4. **Encaisser (F9)** : choisissez le mode (Espèces, Mobile Money, Virement…), saisissez le **montant reçu** (pavé numérique ou montants rapides). La **monnaie à rendre** s'affiche. Paiement en plusieurs modes : *Ajouter ce paiement*, puis compléter.
5. **Valider la vente (Entrée)**. Un seul appui suffit : le bouton est verrouillé pendant l'envoi, et même un double appui ne crée jamais deux ventes.
6. Imprimez le ticket (**80 mm**, **58 mm**, **A4** ou **PDF**), puis *Nouvelle vente (Entrée)*.

Remises et ventes à crédit : uniquement si votre rôle le permet ; une vente à crédit exige un client identifié.

### 3. Hors connexion
Si la connexion tombe, le bandeau orange « Mode hors ligne » apparaît. Continuez à vendre : chaque vente est **provisoire**, enregistrée sur l'appareil, avec un ticket marqué « VENTE PROVISOIRE ». Au retour du réseau, elle est envoyée automatiquement (ou bouton *Ventes hors ligne → Synchroniser maintenant*).
États : **En attente** · **Synchronisée** (numéro définitif) · **En conflit** (un responsable doit décider : prix changé, stock insuffisant…) · **Rejetée**.
⚠ Ne videz pas les données du navigateur tant que des ventes sont en attente. Le stock affiché hors ligne peut être dépassé par les ventes des autres caisses.

### 4. Retour d'un client
*Historique des ventes* → ouvrir la vente → *Retour* → quantités retournées (jamais plus que vendu), état **revendable** ou **endommagé**, **motif**. Si votre rôle ne permet pas d'autoriser, un responsable saisit son identifiant et son mot de passe. Imprimez le justificatif.

### 5. Règlement d'une dette
*Clients* → le client → *Encaisser un règlement* (montant, mode). Le reçu de règlement indique le solde restant.

### 6. Dépense payée en caisse
*Dépenses → Nouvelle dépense*, cochez « payée depuis la caisse » si l'argent sort du tiroir. Sans droit de validation, la dépense reste **en attente** et n'affecte pas la caisse tant qu'un responsable ne l'a pas validée.

### 7. Clôturer sa caisse
*Caisse* → comptez les espèces → saisissez le **montant compté** → l'**écart** s'affiche (justification obligatoire s'il n'est pas nul) → *Clôturer*. Imprimez le **rapport de clôture**. Les paiements électroniques sont listés à part.

---

## Magasinier

- **Produits** : *Produits → Nouvel article* : référence unique, désignation, type (produit ou **service** — un service n'a pas de stock), catégorie, unité, code-barres, photo, prix d'achat, prix de vente, seuil d'alerte, **stock initial** (enregistré comme mouvement « Stock initial »).
- **Import** : *Produits → Importer* : téléchargez le modèle, remplissez-le dans Excel, *Vérifier sans importer*, puis *Importer*. Une seule ligne fausse = rien n'est importé (la liste des erreurs s'affiche).
- **Entrée de stock** : *Entrées de stock → Nouveau bon* : fournisseur, n° de facture/BL du fournisseur, articles, quantités, coûts. Enregistrez en brouillon ; le stock ne change qu'à la **validation** (gérant).
- **Pertes, casses, retours fournisseurs** : *Stock → Perte / retour fournisseur* (motif obligatoire).
- **Inventaire** : ouvrez la session d'inventaire (gérant), comptez **rayon par rayon** et saisissez immédiatement ; justifiez chaque écart. Les ventes continuent pendant l'inventaire.
- **Mouvements** : *Mouvements* montre l'historique complet (qui, quand, pourquoi, quel document).

---

## Gérant

- **Tableau de bord** : ventes du jour, chiffre d'affaires, encaissements, solde de caisse, marge commerciale, dépenses, résultat estimatif, créances, alertes de stock, meilleures ventes. Survolez ⓘ pour la définition de chaque indicateur.
- **Valider** : bons d'entrée, dépenses en attente, inventaires.
- **Annuler une vente** : fiche de la vente → *Annuler la vente* (motif). La vente reste visible ; le stock est réintégré et le client remboursé depuis votre caisse.
- **Conflits hors ligne** : *Synchronisation* → pour chaque vente en conflit, *Accepter* (aux prix du ticket) ou *Rejeter* (motif ; régularisez l'argent perçu).
- **Caisses** : *Sessions de caisse* : toutes les caisses, écarts, rapports de clôture.
- **Rapports** : 14 rapports, filtres période/utilisateur/article/catégorie, exports **Excel**, **CSV**, **PDF**. Le *Compte de résultat estimatif* présente séparément ventes nettes, coût des marchandises vendues, marge et dépenses.
- **Consultation à distance** : vue condensée lisible sur téléphone, avec l'**heure de génération** et la **dernière synchronisation de chaque appareil** (une donnée hors ligne non synchronisée n'y figure pas encore).

---

## Administrateur

- **Utilisateurs** : création, rôle, désactivation, **archivage** (jamais de suppression : l'historique est conservé). Le dernier administrateur actif ne peut pas être désactivé.
- **Rôles et permissions** : cochez les permissions de chaque rôle ; effet immédiat.
- **Paramètres** : informations et logo du commerce, devise principale, seconde devise et taux, fuseau horaire, format de reçu par défaut, message de remerciement, remise maximale, seuil d'alerte par défaut, stock négatif, ventes hors ligne et plafond, modes de paiement, **taxes** (aucune n'est imposée), catégories de dépenses, préfixes de numérotation, **sauvegardes**.
- **Journal d'audit** : toutes les actions sensibles avec auteur, date, adresse IP, valeurs avant/après.

---

## Raccourcis du point de vente

| Touche | Action |
|---|---|
| Entrée (dans la recherche) | ajouter l'article scanné / unique résultat |
| F9 | ouvrir l'encaissement |
| Entrée (encaissement) | valider la vente |
| Entrée (vente terminée) | nouvelle vente |
