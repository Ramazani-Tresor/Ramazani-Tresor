# 1. Périmètre, hypothèses et règles de gestion

## 1.1 Synthèse du périmètre

**Biashara** (« commerce » en swahili) est une application web de gestion commerciale pour une petite ou moyenne entreprise : point de vente, stock, caisse, clients et créances, fournisseurs et approvisionnements, retours, dépenses, inventaires, rapports, consultation à distance et fonctionnement hors ligne du point de vente.

L'interface reprend l'organisation par grandes tuiles (Ventes, Produits, Clients, Stock, Dépenses, Rapports, Inventaire, Paramètres…) avec une identité visuelle propre (bleu nuit `#0B1F3A`, bleu vif `#1E6FD9`, bleu clair `#E8F1FC`, blanc) et un logo original (sac de commerce + barres de croissance). Aucune marque tierce n'est reproduite.

| Domaine | Livré |
|---|---|
| Tableau de bord | Ventes du jour et de la période, encaissements, solde de caisse, dépenses, marge, résultat estimatif, créances, alertes de stock, meilleures ventes, dernières transactions, graphique ventes/dépenses |
| Catalogue | Catégories (désactivation, suppression interdite si utilisée), produits et services, code-barres, photo, unités, taxes, import Excel/CSV contrôlé |
| Ventes | Point de vente tactile + scanner, remises sous permission, taxes, paiements multiples (espèces, Mobile Money, virement, carte, modes configurables), seconde devise avec taux, crédit et paiement partiel, monnaie rendue, reçus 58/80 mm/A4/PDF, duplicata |
| Stock | Journal des mouvements (stock initial, entrées, ventes, retours, retours fournisseurs, pertes, ajustements d'inventaire, mise au rebut), stock endommagé séparé, CMP mobile |
| Achats | Fournisseurs, bons d'entrée multi-lignes (brouillon → validation), référence fournisseur, impression du bon |
| Retours | Retour partiel/total, contrôle des quantités, motif obligatoire, autorisation tracée, revendable/endommagé, remboursement borné, annulation par opérations correctrices |
| Clients | Fiche, historique, ventes à crédit, plafond de crédit, règlements idempotents, relevé de compte |
| Caisse | Ouverture avec fonds initial, une session par caissier, apports/retraits, espèces vs électronique, clôture avec écart justifié, rapport de clôture |
| Dépenses | Catégories, justificatif privé, validation selon permission, impact caisse uniquement si payée depuis la caisse, bon de dépense |
| Inventaire | Session, stock théorique au relevé, saisie, écarts justifiés, validation responsable, ajustements traçables, export Excel |
| Rapports | 14 rapports filtrables (période, utilisateur, article, catégorie), exports Excel, CSV et PDF |
| Administration | Utilisateurs (archivage), rôles et permissions configurables, paramètres, numérotation, sauvegardes, journal d'audit |
| Hors ligne | PWA, Service Worker, IndexedDB, file de ventes, synchronisation idempotente, gestion des conflits |

## 1.2 Hypothèses retenues (informations manquantes)

1. **Mono-établissement, mono-dépôt** : un seul point de stock. Plusieurs caisses (postes) peuvent fonctionner simultanément.
2. **Devise principale CDF** (symbole FC), seconde devise **USD** désactivable ; taux par défaut `2800` **à remplacer** par le taux réel. Les montants sont stockés en devise principale ; chaque paiement conserve la devise remise, le montant dans cette devise et le taux appliqué.
3. **Aucune taxe n'est préconfigurée** : l'administrateur crée les taux qui lui sont applicables et les associe aux articles. Les prix saisis sont **hors taxe** ; la taxe s'ajoute à la ligne.
4. **Fuseau horaire** `Africa/Lubumbashi` par défaut.
5. Les **fournisseurs ne sont pas suivis en comptes fournisseurs** (dettes fournisseurs) : un bon peut être marqué « payé en espèces depuis la caisse » ; sinon le paiement est hors application.
6. Les **remises** sont exprimées par ligne (montant ou %), plafonnées par paramètre (20 % par défaut), sauf pour l'administrateur.
7. Les **retours endommagés** ne sont jamais remis en vente ; ils peuvent ensuite être mis au rebut (sortie du stock endommagé).
8. Le **résultat estimatif** est un indicateur de gestion, **non comptable** (pas d'amortissements, pas d'impôts).
9. Ventes **hors ligne** : autorisées par défaut, sans plafond (paramétrables).

## 1.3 Acteurs et responsabilités

| Acteur | Responsabilités principales | Permissions par défaut |
|---|---|---|
| **Administrateur** | Accès complet, utilisateurs, rôles, paramètres, sauvegardes, journal d'audit | toutes (non modifiables) |
| **Gérant** | Supervision : ventes, annulations, remises, crédit, stocks, approvisionnements (validation), inventaires (validation), dépenses (validation), caisses, rapports, conflits de synchronisation, consultation à distance | tout sauf administration |
| **Caissier** | Ventes, encaissements, reçus, ses ventes, sa caisse, clients et règlements de créances, saisie de retours (avec autorisation d'un responsable), saisie de dépenses (en attente) | ventes, caisse, clients |
| **Magasinier** | Produits, catégories, fournisseurs, bons d'entrée (saisie), pertes/retours fournisseurs, comptages d'inventaire | catalogue, stock |
| **Client** (externe) | Achète, paie, règle ses dettes, retourne des articles | — |
| **Appareil hors ligne** | Enregistre des ventes provisoires, les synchronise | — |

Les permissions (43) sont modifiables par rôle dans *Administration → Rôles et permissions*. Elles sont **contrôlées côté serveur** (middleware `can:` sur chaque route + vérifications dans les contrôleurs et services) ; masquer un bouton n'est jamais la seule protection.

## 1.4 Règles de gestion

### Ventes
- **RG-V1** Chaque vente reçoit un numéro unique séquentiel (`VTE-2026-000123`), attribué sous verrou.
- **RG-V2** Chaque vente porte un identifiant généré par l'appareil (`client_uuid`, unique en base) : double clic, re-tentative après coupure ou nouvelle synchronisation renvoient la vente existante sans en créer une seconde.
- **RG-V3** Une vente validée crée **dans une seule transaction** : la vente, ses lignes, ses paiements, ses mouvements de stock et ses mouvements de caisse. En cas d'erreur, rien n'est enregistré.
- **RG-V4** Les lignes conservent l'instantané de l'opération : référence, désignation, unité, prix, remise, nom et taux de taxe, total, **coût de sortie (CMP)**. Modifier un article n'altère jamais un ancien reçu.
- **RG-V5** Le serveur recalcule tous les montants à partir du catalogue (le navigateur n'est pas une source de vérité).
- **RG-V6** Toute vente est rattachée à une **session de caisse ouverte** du vendeur.
- **RG-V7** Remise : permission `sales.discount`, ≤ montant de la ligne, ≤ plafond paramétré (hors administrateur).
- **RG-V8** Paiement insuffisant : permission `sales.credit` **et** client identifié requis ; plafond de crédit du client respecté.
- **RG-V9** La monnaie se rend en espèces uniquement : les paiements électroniques ne peuvent pas dépasser le net à payer. Le paiement espèces est enregistré net de la monnaie rendue.
- **RG-V10** Une vente validée **n'est jamais supprimée** ; l'annulation génère un retour total (remise en stock), l'extinction de la créance et le remboursement de la part payée.

### Stock
- **RG-S1** Le stock ne varie que par un mouvement (`stock_movements`) portant : article, type, quantité signée, solde après, coût unitaire, motif, utilisateur, date, document d'origine (type, id, numéro).
- **RG-S2** Le stock initial est un mouvement de type « Stock initial ».
- **RG-S3** Les services ne génèrent aucun mouvement.
- **RG-S4** Stock négatif interdit par défaut (paramétrable). Contrôle effectué **après verrouillage** des lignes article (`SELECT … FOR UPDATE`, ordre croissant des identifiants pour éviter les interblocages) : deux caissiers ne peuvent pas vendre le même dernier article.
- **RG-S5** Deux compartiments : *vendable* et *endommagé*. Un retour endommagé alimente le compartiment endommagé.
- **RG-S6** Valorisation au **coût moyen pondéré mobile** : à chaque entrée valorisée (stock initial, approvisionnement, retour revendable, annulation), `CMP = (Q_avant × CMP_avant + Q_entrée × coût_entrée) / (Q_avant + Q_entrée)` ; si le stock avant est ≤ 0, CMP = coût d'entrée. Les sorties sont valorisées au CMP courant, figé dans le mouvement et dans la ligne de vente.
- **RG-S7** La commande `php artisan app:check-stock` vérifie que chaque quantité est égale à la somme de ses mouvements.

### Approvisionnements
- **RG-A1** Bon en brouillon modifiable ; la **validation** crée les mouvements d'entrée et met à jour le CMP ; un bon validé n'est plus modifiable (erreur → retour fournisseur).
- **RG-A2** Les approvisionnements **ne sont pas des dépenses** : leur coût passe dans le coût des marchandises vendues au fil des ventes (pas de double comptabilisation).

### Retours
- **RG-R1** Quantité retournée ≤ quantité vendue − retours précédents (contrôlé sous verrou).
- **RG-R2** Motif obligatoire ; l'utilisateur qui saisit et celui qui **autorise** sont enregistrés. Sans la permission `returns.authorize`, un responsable doit saisir ses identifiants.
- **RG-R3** La valeur retournée (TTC nette de remise) éteint d'abord la créance de la vente ; le reliquat est remboursable dans la limite de ce que le client a payé et non déjà remboursé.
- **RG-R4** Le remboursement sort de la caisse ouverte de l'utilisateur ; impossible si les espèces théoriques sont insuffisantes.

### Clients et créances
- **RG-C1** Créance = somme des `amount_due` des ventes validées.
- **RG-C2** Un règlement est un document distinct (`REG-…`), sans ligne ni stock, imputé sur les ventes les plus anciennes (ou une vente choisie). Il ne peut excéder le solde dû.
- **RG-C3** Identifiant de règlement généré par le formulaire : une double soumission ne compte qu'un règlement.

### Caisse
- **RG-K1** Une seule session ouverte par caissier (contrainte d'unicité `open_lock`).
- **RG-K2** Espèces théoriques = fonds initial + Σ mouvements espèces (ventes nettes de monnaie, règlements, apports) − sorties (remboursements, dépenses, paiements fournisseurs, retraits).
- **RG-K3** Les paiements électroniques sont rattachés à la session mais hors tiroir.
- **RG-K4** Clôture : montant compté saisi, écart = compté − théorique ; justification obligatoire si écart ≠ 0. Aucune opération sur une session clôturée.

### Dépenses
- **RG-D1** Saisie par un utilisateur autorisé ; validation immédiate si l'auteur a `expenses.approve`, sinon statut « en attente ».
- **RG-D2** Une dépense n'affecte la caisse que si elle est **validée et payée depuis la caisse** (mode espèces) : le décaissement est imputé à la session ouverte du valideur.

### Inventaire (procédure retenue)
- **RG-I1** Les ventes **ne sont pas bloquées** pendant l'inventaire.
- **RG-I2** Pour chaque article, le stock théorique est **figé au moment de la saisie du comptage** (pas à l'ouverture).
- **RG-I3** Écart = compté − théorique au relevé ; justification obligatoire pour tout écart.
- **RG-I4** À la validation (permission `inventories.validate`), l'écart est appliqué au stock courant par un mouvement « Ajustement d'inventaire ». Les ventes et entrées intervenues entre le relevé et la validation restent ainsi correctement prises en compte (testé).
- **RG-I5** Deux inventaires ouverts ne peuvent pas couvrir les mêmes articles.

### Sécurité et traçabilité
- **RG-X1** Les articles, clients et utilisateurs liés à des transactions sont **désactivés/archivés**, jamais supprimés.
- **RG-X2** Journal d'audit : connexions (réussies/échouées), ventes, annulations, retours, réimpressions, règlements, caisse, stock, prix, permissions, paramètres, exports, sauvegardes.

## 1.5 Définition des indicateurs

| Indicateur | Définition |
|---|---|
| **Chiffre d'affaires TTC** | Σ totaux des ventes de la période − Σ valeurs des retours/annulations de la période |
| **Ventes nettes HT** | Σ (total ligne − taxe) − part HT des retours |
| **Coût des marchandises vendues (CMV)** | Σ quantité vendue × coût de sortie (CMP figé) − Σ quantité retournée revendable × même coût |
| **Marge commerciale** | Ventes nettes HT − CMV. Les dépenses **n'y sont pas** déduites |
| **Dépenses d'exploitation** | Σ dépenses validées dont la date est dans la période (hors approvisionnements) |
| **Pertes de stock nettes** | Valeur des pertes/casses + écarts d'inventaire (négatifs − positifs) au coût |
| **Résultat estimatif** | Marge commerciale − dépenses d'exploitation − pertes de stock nettes |
| **Encaissements** | Σ paiements reçus (ventes + règlements de créances) − remboursements, sur la période |
| **Créances clients** | Σ restes dus des ventes validées, à l'instant présent |
| **Solde de caisse** | Σ espèces théoriques des sessions ouvertes |

Le chiffre d'affaires (ce qui est vendu) se distingue des encaissements (ce qui est reçu) : une vente à crédit augmente le premier mais pas le second ; un règlement de créance augmente le second mais pas le premier.

## 1.6 Mode en ligne / hors ligne

**Disponible hors ligne** (si le point de vente a été ouvert au moins une fois sur l'appareil) : consultation du catalogue synchronisé (avec sa date), préparation du panier, encaissement, vente provisoire, ticket provisoire, recherche parmi les clients déjà consultés, file des ventes en attente.

**Non disponible hors ligne** : création de client, retours, annulations, règlements de créances, caisse (ouverture/clôture), stock, rapports, administration.

**Politique retenue : vente provisoire soumise à validation lors de la synchronisation.**
Un appareil déconnecté ne connaît **pas** le stock réellement disponible : d'autres caisses peuvent avoir vendu les mêmes articles. Chaque vente hors ligne reçoit un UUID généré sur l'appareil et est stockée dans IndexedDB (état *en attente*). Au retour du réseau, elle est rejouée par le serveur :

- aucune anomalie → vente créée, état **synchronisée** (numéro définitif renvoyé à l'appareil) ;
- prix modifié entre-temps, stock insuffisant, article désactivé, caisse fermée, plafond hors ligne dépassé… → état **en conflit**. La vente **n'est pas modifiée silencieusement** : un responsable l'**accepte** (aux prix du ticket, que le client a payés) ou la **rejette** (motif obligatoire ; l'argent perçu doit être régularisé) ;
- un UUID déjà traité n'est **jamais** traité deux fois (contrainte d'unicité + contrôle applicatif).

La synchronisation sans conflit n'est **pas garantie** : c'est une conséquence inévitable du fonctionnement déconnecté. Le plafond par vente hors ligne et la désactivation complète du mode hors ligne sont paramétrables.
