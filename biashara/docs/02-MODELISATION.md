# 2. Modélisation

Les diagrammes sont écrits en [Mermaid](https://mermaid.js.org/) : ils s'affichent directement sur GitHub.

## 2.1 Cas d'utilisation

```mermaid
flowchart LR
    ADM([Administrateur])
    GER([Gérant])
    CAI([Caissier])
    MAG([Magasinier])
    DEV([Appareil hors ligne])

    subgraph Ventes
        UC1[Encaisser une vente]
        UC2[Accorder une remise]
        UC3[Vendre à crédit / paiement partiel]
        UC4[Imprimer / réimprimer un reçu]
        UC5[Enregistrer un retour]
        UC6[Autoriser un retour / remboursement]
        UC7[Annuler une vente]
        UC8[Encaisser un règlement de créance]
    end
    subgraph Caisse
        UC9[Ouvrir / clôturer sa caisse]
        UC10[Apport / retrait d'espèces]
        UC11[Consulter toutes les caisses]
    end
    subgraph Stock
        UC12[Gérer produits et catégories]
        UC13[Importer des articles]
        UC14[Saisir un bon d'entrée]
        UC15[Valider un bon d'entrée]
        UC16[Déclarer perte / retour fournisseur]
        UC17[Compter un inventaire]
        UC18[Valider un inventaire]
    end
    subgraph Gestion
        UC19[Saisir une dépense]
        UC20[Valider une dépense]
        UC21[Consulter tableau de bord et rapports]
        UC22[Consultation à distance]
        UC23[Traiter les conflits de synchronisation]
    end
    subgraph Administration
        UC24[Gérer utilisateurs et rôles]
        UC25[Paramétrer le commerce]
        UC26[Sauvegarder / consulter l'audit]
    end
    UC27[Vendre hors ligne puis synchroniser]

    CAI --- UC1 & UC4 & UC5 & UC8 & UC9 & UC10 & UC19
    GER --- UC1 & UC2 & UC3 & UC6 & UC7 & UC11 & UC15 & UC18 & UC20 & UC21 & UC22 & UC23
    MAG --- UC12 & UC13 & UC14 & UC16 & UC17
    ADM --- UC24 & UC25 & UC26
    ADM -. hérite de tout .- GER
    CAI --- UC27
    DEV --- UC27
    UC27 -. conflit .-> UC23
```

## 2.2 Diagramme de classes (domaine)

```mermaid
classDiagram
    direction LR
    class User {
        name
        username
        password_hache
        is_active
        archived_at
    }
    class Role {
        slug
        name
    }
    class Permission {
        slug
        name
        group
    }
    class Category {
        name
        is_active
    }
    class Unit {
        name
        symbol
        allows_decimal
    }
    class Tax {
        name
        rate
    }
    class Product {
        reference
        name
        type
        barcode
        cost_price
        average_cost
        sale_price
        alert_threshold
        stock_quantity
        damaged_quantity
        is_active
    }
    class Supplier {
        name
        phone
        is_active
    }
    class Customer {
        code
        name
        phone
        credit_limit
        balanceDue()
    }
    class Purchase {
        number
        supplier_reference
        purchase_date
        status
        total
        paid_from_cash
    }
    class PurchaseItem {
        quantity
        unit_cost
        line_total
    }
    class Sale {
        number
        client_uuid
        status
        origin
        subtotal
        discount_total
        tax_total
        total
        amount_paid
        amount_due
        change_given
        returned_total
        refunded_total
        currency
    }
    class SaleItem {
        product_name_fige
        unit_price_fige
        discount_amount
        tax_rate_fige
        tax_amount
        line_total
        unit_cost_fige
        returned_quantity
    }
    class Payment {
        kind
        amount
        currency
        currency_amount
        exchange_rate
    }
    class CustomerSettlement {
        number
        client_uuid
        amount
    }
    class SaleReturn {
        number
        reason
        total_value
        due_reduction
        refund_amount
        is_cancellation
    }
    class SaleReturnItem {
        quantity
        condition
        line_value
        unit_cost
    }
    class StockMovement {
        type
        bucket
        quantity_signee
        unit_cost
        balance_after
        reason
        source
        source_number
    }
    class CashSession {
        number
        status
        opening_amount
        expected_cash
        counted_cash
        difference
    }
    class CashMovement {
        type
        amount_signe
        source
    }
    class PaymentMethod {
        code
        name
        kind
    }
    class Expense {
        number
        amount
        beneficiary
        reason
        paid_from_cash
        status
        attachment_path
    }
    class ExpenseCategory {
        name
    }
    class Inventory {
        number
        status
    }
    class InventoryItem {
        theoretical_quantity
        counted_quantity
        difference
        justification
    }
    class SyncOperation {
        client_uuid
        status
        payload
        conflicts
    }
    class Device {
        device_id
        last_seen_at
        last_sync_at
    }
    class AuditLog {
        action
        description
        old_values
        new_values
        ip_address
    }

    Role "1" -- "*" User
    Role "*" -- "*" Permission
    Category "1" -- "*" Product
    Unit "1" -- "*" Product
    Tax "0..1" -- "*" Product
    Supplier "1" -- "*" Purchase
    Purchase "1" *-- "*" PurchaseItem
    PurchaseItem "*" --> "1" Product
    Customer "0..1" -- "*" Sale
    User "1" -- "*" Sale : vendeur
    CashSession "1" -- "*" Sale
    Sale "1" *-- "*" SaleItem
    SaleItem "*" --> "1" Product
    Sale "1" -- "*" Payment
    CustomerSettlement "1" -- "*" Payment : imputations
    Sale "1" -- "*" SaleReturn
    SaleReturn "1" *-- "*" SaleReturnItem
    SaleReturnItem "*" --> "1" SaleItem
    Product "1" -- "*" StockMovement
    User "1" -- "*" CashSession
    CashSession "1" *-- "*" CashMovement
    PaymentMethod "1" -- "*" CashMovement
    PaymentMethod "1" -- "*" Payment
    ExpenseCategory "1" -- "*" Expense
    Inventory "1" *-- "*" InventoryItem
    InventoryItem "*" --> "1" Product
    SyncOperation "0..1" --> "1" Sale : résultat
```
Les attributs suffixés `_fige` sont des valeurs figées au moment de la vente (instantané). `kind` : sale | debt_settlement | refund ; `bucket` : available | damaged ; `condition` : resaleable | damaged.

Les **services** portent la logique métier (un service = un processus transactionnel) : `SaleService`, `ReturnService`, `SettlementService`, `StockService`, `CashService`, `PurchaseService`, `ExpenseService`, `InventoryService`, `SyncService`, `ProductService`, `NumberingService`, `FinancialMetrics`, `ReportService`, `BackupService`, `AuditService`.

## 2.3 Diagramme de séquence — validation d'une vente

```mermaid
sequenceDiagram
    autonumber
    actor C as Caissier
    participant UI as Point de vente (navigateur)
    participant IDB as IndexedDB
    participant API as PosController (POST /api/ventes)
    participant S as SaleService
    participant DB as MySQL (transaction)

    C->>UI: Scanne / touche les articles, saisit le paiement
    UI->>UI: Calcul exact en entiers (BigInt), contrôle monnaie / crédit
    C->>UI: Valider (bouton verrouillé pendant l'envoi)
    UI->>API: {client_uuid, articles, paiements, client}
    API->>API: Auth + permission sales.create + validation
    API->>S: create(données, vendeur)
    S->>DB: SELECT vente WHERE client_uuid (idempotence)
    alt déjà enregistrée (double clic / re-tentative)
        DB-->>S: vente existante
        S-->>API: [vente, duplicate=true]
    else nouvelle vente
        S->>DB: BEGIN
        S->>DB: SELECT session caisse ouverte FOR UPDATE
        S->>DB: SELECT produits ... ORDER BY id FOR UPDATE
        S->>S: Recalcul prix, remises, taxes, total, monnaie, reste dû
        S->>DB: Contrôle stock (après verrou) — sinon ROLLBACK + message
        S->>DB: Numéro VTE-… (séquence FOR UPDATE)
        S->>DB: INSERT sales, sale_items (instantanés + CMP)
        S->>DB: INSERT stock_movements, UPDATE products.stock_quantity
        S->>DB: INSERT payments, cash_movements
        S->>DB: INSERT audit_logs
        S->>DB: COMMIT
        S-->>API: [vente, duplicate=false]
    end
    API-->>UI: 201 {numéro, total, monnaie, reste dû, lien reçu}
    UI-->>C: Monnaie à rendre + impression 80/58 mm/A4/PDF
    Note over UI,IDB: Si le réseau échoue : la même requête (même client_uuid)<br/>est placée dans IndexedDB (vente provisoire) puis<br/>rejouée par /api/sync/ventes au retour de la connexion.
```

## 2.4 Schéma relationnel

Conventions : `id` BIGINT UNSIGNED AUTO_INCREMENT clé primaire ; `created_at`/`updated_at` ; moteur InnoDB, `utf8mb4`. Montants `DECIMAL(15,2)`, quantités `DECIMAL(15,3)`, coûts moyens `DECIMAL(15,4)`, taux `DECIMAL(7,4)`/`DECIMAL(15,4)`. **Aucun type flottant.**

| Table | Colonnes principales | Clés étrangères | Contraintes / index |
|---|---|---|---|
| `roles` | slug, name, description, is_system | — | UNIQUE(slug) |
| `permissions` | slug, name, group | — | UNIQUE(slug), INDEX(group) |
| `permission_role` | role_id, permission_id | → roles CASCADE, → permissions CASCADE | PK(role_id, permission_id) |
| `users` | name, username, email, phone, password, role_id, is_active, last_login_at, archived_at | → roles RESTRICT | UNIQUE(username), UNIQUE(email) |
| `settings` | key, value | — | UNIQUE(key) |
| `document_sequences` | type, label, prefix, next_number, padding, yearly_reset, current_year | — | UNIQUE(type) — verrouillée à chaque attribution |
| `payment_methods` | code, name, kind(cash/electronic), is_active, sort_order | — | UNIQUE(code) |
| `taxes` | name, rate, is_active | — | — |
| `categories` | name, description, color, is_active | — | UNIQUE(name), INDEX(is_active) |
| `units` | name, symbol, allows_decimal | — | UNIQUE(name) |
| `products` | reference, name, type, category_id, unit_id, tax_id, description, barcode, photo_path, cost_price, average_cost, sale_price, alert_threshold, stock_quantity, damaged_quantity, is_active | → categories RESTRICT, → units RESTRICT, → taxes SET NULL | UNIQUE(reference), UNIQUE(barcode), INDEX(name), INDEX(category_id, is_active) |
| `suppliers` | name, contact_person, phone, email, address, is_active | — | INDEX(name) |
| `customers` | code, name, phone, email, address, credit_limit, is_active | — | UNIQUE(code), INDEX(name), INDEX(phone) |
| `cash_sessions` | number, user_id, status, opening_amount, opened_at, expected_cash, counted_cash, difference, electronic_total, closing_notes, closed_at, closed_by, open_lock | → users RESTRICT | UNIQUE(number), **UNIQUE(open_lock)** = une session ouverte par caissier, INDEX(user_id, status) |
| `cash_movements` | cash_session_id, payment_method_id, type, amount±, description, source_type/id, user_id | → cash_sessions, payment_methods, users RESTRICT | INDEX(type), INDEX(source) |
| `sales` | number, client_uuid, customer_id, user_id, cash_session_id, status, origin, device_id, subtotal, discount_total, tax_total, total, amount_paid, amount_due, amount_received, change_given, returned_total, refunded_total, currency, exchange_rate, print_count, sold_at, cancelled_* | → customers, users, cash_sessions RESTRICT | UNIQUE(number), **UNIQUE(client_uuid)**, INDEX(status), INDEX(sold_at), INDEX(user_id, sold_at), INDEX(customer_id, amount_due) |
| `sale_items` | sale_id, product_id, product_reference*, product_name*, product_type*, unit_symbol*, quantity, unit_price*, discount_amount, tax_name*, tax_rate*, tax_amount, line_total, unit_cost*, returned_quantity | → sales CASCADE, → products RESTRICT | — |
| `customer_settlements` | number, client_uuid, customer_id, payment_method_id, cash_session_id, user_id, amount, reference, paid_at | → customers, payment_methods, cash_sessions, users | UNIQUE(number), **UNIQUE(client_uuid)** |
| `sale_returns` | number, client_uuid, sale_id, user_id, authorized_by, cash_session_id, refund_method_id, reason, total_value, due_reduction, refund_amount, is_cancellation, returned_at | → sales, users ×2, cash_sessions, payment_methods | UNIQUE(number), UNIQUE(client_uuid) |
| `sale_return_items` | sale_return_id, sale_item_id, product_id, quantity, condition, unit_value, line_value, unit_cost | → sale_returns CASCADE, → sale_items, products | — |
| `payments` | kind, sale_id, customer_settlement_id, sale_return_id, customer_id, payment_method_id, cash_session_id, user_id, amount±, currency, currency_amount, exchange_rate, reference, paid_at | → sales, customer_settlements, sale_returns, customers, payment_methods, cash_sessions, users | INDEX(paid_at), INDEX(kind, paid_at) |
| `purchases` | number, supplier_id, supplier_reference, purchase_date, status, total, paid_from_cash, notes, user_id, validated_by, validated_at | → suppliers, users | UNIQUE(number), INDEX(status) |
| `purchase_items` | purchase_id, product_id, quantity, unit_cost, line_total | → purchases CASCADE, → products | — |
| `stock_movements` | product_id, type, bucket, quantity±, unit_cost, balance_after, reason, source_type/id, source_number, user_id, occurred_at | → products, users RESTRICT | INDEX(type), INDEX(occurred_at), INDEX(product_id, occurred_at), INDEX(source) |
| `inventories` | number, category_id, status, notes, opened_by, validated_by, opened_at, validated_at | → categories, users | UNIQUE(number), INDEX(status) |
| `inventory_items` | inventory_id, product_id, theoretical_quantity, counted_quantity, difference, unit_cost, justification, counted_by, counted_at, last_movement_id | → inventories CASCADE, → products | UNIQUE(inventory_id, product_id) |
| `expense_categories` | name, is_active | — | UNIQUE(name) |
| `expenses` | number, expense_category_id, expense_date, amount, beneficiary, reason, payment_method_id, paid_from_cash, cash_session_id, attachment_path, status, user_id, approved_by, approved_at, rejection_reason | → expense_categories, payment_methods, cash_sessions, users | UNIQUE(number), INDEX(expense_date), INDEX(status) |
| `audit_logs` | user_id, action, auditable_type/id, description, old_values(JSON), new_values(JSON), ip_address, user_agent, created_at | → users SET NULL | INDEX(action), INDEX(created_at) |
| `devices` | device_id, label, user_id, user_agent, last_seen_at, last_sync_at | → users SET NULL | UNIQUE(device_id) |
| `sync_operations` | client_uuid, type, device_id, user_id, payload(JSON), status, conflicts(JSON), message, result_type/id, created_offline_at, attempts, resolved_by, resolved_at, resolution_note | → users | **UNIQUE(client_uuid)**, INDEX(status), INDEX(device_id) |

`*` : instantané figé à la vente. Tables techniques Laravel : `sessions`, `cache`, `jobs`, `password_reset_tokens`.

## 2.5 Justification des choix importants

- **Quantité dénormalisée + journal** : `products.stock_quantity` permet des contrôles rapides et un verrou de ligne pour la concurrence ; le journal `stock_movements` en est la justification complète. Les deux sont toujours modifiés dans la même transaction par `StockService` (seul point d'écriture, attributs non assignables en masse), et `app:check-stock` vérifie leur égalité.
- **Verrouillage pessimiste** (`FOR UPDATE`) plutôt qu'optimiste : sur un point de vente, mieux vaut attendre quelques millisecondes que d'échouer ; l'ordre de verrouillage par identifiant croissant évite les interblocages.
- **Idempotence par UUID généré côté client** : même mécanisme pour le double clic, la perte de réponse réseau et la synchronisation hors ligne.
- **Paiements unifiés** (`payments` avec `kind`) : encaissements de vente, règlements de créances et remboursements alimentent les mêmes rapports d'encaissements, sans confondre règlement et vente.
- **Retour = mécanisme unique de correction** : l'annulation est un retour total ; il n'existe aucune suppression de vente.
- **Compartiment « endommagé »** dans le même journal (`bucket`) : traçabilité complète sans table supplémentaire.
- **Bibliothèque `brick/math`** (déjà dépendance de Laravel) pour tous les calculs : pas de dépendance à l'extension `bcmath`, souvent absente des installations XAMPP.
- **Laravel 12** (et non 11, en fin de support et visé par des avis de sécurité) : compatible PHP 8.2 fourni par XAMPP.
