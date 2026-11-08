# Intégrations matérielles : possibilités et limites

L'application fonctionne dans un **navigateur**. Elle n'exige aucun pilote ni logiciel spécifique, mais un navigateur n'a pas d'accès direct et universel au matériel. Ce qui suit distingue ce qui fonctionne **sans rien ajouter** de ce qui nécessite un composant supplémentaire.

## Lecteur de codes-barres — ✅ fonctionne tel quel
Les lecteurs USB ou Bluetooth en mode **HID (émulation clavier)** — le mode par défaut de la plupart des douchettes — « tapent » le code puis Entrée dans le champ de recherche du point de vente : l'article est ajouté directement. Réglez le lecteur pour envoyer le suffixe **Entrée** (CR). Les codes EAN-13, EAN-8, UPC et Code 128 sont acceptés tels quels (le code doit être identique au champ *Code-barres* de l'article).
La lecture par **caméra** du téléphone n'est pas incluse (elle nécessiterait une bibliothèque de décodage et HTTPS).

## Impression — impression du navigateur (recommandée)
Les reçus ont trois mises en page dédiées : **A4**, **ticket 80 mm** (zone imprimable 72 mm) et **ticket 58 mm** (48 mm), sélectionnables à l'écran, plus l'export **PDF**.
- **Imprimante thermique USB sous Windows** : installez le pilote du fabricant, définissez un format de papier 80 mm (ou 58 mm) × longueur continue, marges nulles ; dans la boîte d'impression : *Marges : aucune*, *En-têtes et pieds de page : désactivés*, *Échelle : 100 %*.
- **Impression sans boîte de dialogue** (poste de caisse dédié) : lancez Chrome/Edge avec l'option `--kiosk-printing` et définissez l'imprimante thermique comme imprimante par défaut.
- **Tiroir-caisse** : il s'ouvre via l'imprimante thermique si le pilote propose l'option « ouvrir le tiroir à l'impression ». Le navigateur ne peut pas envoyer de commande directe au tiroir.

## Terminal Android et imprimante Bluetooth — ⚠ composant supplémentaire nécessaire
Un navigateur Android **ne sait pas** imprimer directement sur une imprimante thermique Bluetooth (pas d'accès Bluetooth Classic/SPP depuis une page web ; Web Bluetooth est limité au Bluetooth Low Energy, absent ou incomplet sur la plupart des imprimantes de caisse et non disponible sur iOS).
Solutions éprouvées :
1. **Service d'impression Android** : installez l'application du fabricant ou un service tel que *RawBT* ; il apparaît dans le menu *Imprimer* de Chrome. Le ticket 58/80 mm s'imprime alors depuis l'application sans modification.
2. **Terminal POS Android avec imprimante intégrée** (Sunmi, iMin…) : utilisez le service d'impression fourni par le constructeur (même principe).
3. **Partage PDF** : bouton *PDF* puis impression via l'application de l'imprimante.
4. Pour aller plus loin (impression ESC/POS directe, ouverture de tiroir) : une petite application Android « enveloppe » (WebView + SDK d'impression du constructeur) ou un service local d'impression sur le poste Windows ; ce développement n'est pas inclus.

## Écran client, balance, TPE bancaire
Non intégrés. Une balance ou un TPE nécessiteraient un logiciel passerelle local (port série/USB) : le navigateur n'y a pas accès de manière fiable. La saisie de quantités décimales (kg, L) et de références de transaction (Mobile Money, carte) est prévue manuellement.

## Mobile Money
Le mode « Mobile Money » enregistre le montant et la référence de transaction saisie par le caissier. Aucune connexion à l'API d'un opérateur n'est incluse (elle nécessite un contrat marchand et des identifiants propres à chaque opérateur).

## Compatibilité navigateurs
| Fonction | Chrome/Edge (Windows, Android) | Firefox | Safari iOS |
|---|---|---|---|
| Application, reçus, PDF | ✅ | ✅ | ✅ |
| Hors ligne (Service Worker + IndexedDB) | ✅ (HTTPS ou localhost) | ✅ (HTTPS) | ✅ (HTTPS, stockage pouvant être purgé par le système) |
| Installation « application » (PWA) | ✅ | partiel | ✅ (Ajouter à l'écran d'accueil) |
