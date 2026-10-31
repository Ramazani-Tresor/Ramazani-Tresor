<?php

namespace App\Support;

/**
 * Catalogue des permissions et attribution par défaut aux rôles.
 * Les permissions sont vérifiées côté serveur (middleware `can:` + Gate), pas seulement dans l'affichage.
 */
final class PermissionRegistry
{
    public const PERMISSIONS = [
        'Tableau de bord' => [
            'dashboard.view' => 'Voir le tableau de bord',
            'dashboard.financial' => 'Voir les indicateurs financiers (marge, résultat)',
            'remote.view' => 'Consultation à distance',
        ],
        'Catalogue' => [
            'categories.manage' => 'Gérer les catégories',
            'products.view' => 'Consulter les produits et services',
            'products.manage' => 'Créer et modifier les produits',
            'products.import' => 'Importer des produits (Excel/CSV)',
            'products.view_cost' => 'Voir les coûts d\'achat',
        ],
        'Ventes' => [
            'sales.create' => 'Réaliser des ventes (point de vente)',
            'sales.view' => 'Consulter toutes les ventes',
            'sales.view_own' => 'Consulter ses propres ventes',
            'sales.discount' => 'Accorder des remises',
            'sales.credit' => 'Vendre à crédit / paiement partiel',
            'sales.cancel' => 'Annuler une vente',
            'sales.reprint' => 'Réimprimer un reçu (duplicata)',
            'returns.create' => 'Enregistrer des retours clients',
            'returns.authorize' => 'Autoriser des retours et remboursements',
        ],
        'Clients' => [
            'customers.view' => 'Consulter les clients et créances',
            'customers.manage' => 'Créer et modifier les clients',
            'customers.settle' => 'Encaisser des règlements de créances',
        ],
        'Stock' => [
            'stock.view' => 'Consulter le stock et les mouvements',
            'stock.adjust' => 'Enregistrer pertes, casses et retours fournisseurs',
            'suppliers.manage' => 'Gérer les fournisseurs',
            'purchases.view' => 'Consulter les approvisionnements',
            'purchases.create' => 'Saisir des bons d\'entrée',
            'purchases.validate' => 'Valider des bons d\'entrée',
            'inventories.view' => 'Consulter les inventaires',
            'inventories.count' => 'Saisir les comptages d\'inventaire',
            'inventories.validate' => 'Ouvrir et valider les inventaires',
        ],
        'Caisse et dépenses' => [
            'cash.use' => 'Ouvrir et utiliser sa caisse',
            'cash.view_all' => 'Consulter toutes les caisses',
            'cash.close_any' => 'Clôturer la caisse d\'un autre utilisateur',
            'expenses.view' => 'Consulter les dépenses',
            'expenses.create' => 'Saisir des dépenses',
            'expenses.approve' => 'Valider ou rejeter des dépenses',
        ],
        'Rapports' => [
            'reports.view' => 'Consulter les rapports',
            'reports.financial' => 'Rapports financiers (marges, coûts)',
            'reports.export' => 'Exporter les rapports (PDF/Excel)',
        ],
        'Administration' => [
            'users.manage' => 'Gérer les utilisateurs',
            'roles.manage' => 'Gérer les rôles et permissions',
            'settings.manage' => 'Modifier les paramètres',
            'audit.view' => 'Consulter le journal d\'audit',
            'sync.resolve' => 'Traiter les conflits de synchronisation',
        ],
    ];

    /** L'administrateur a implicitement toutes les permissions (voir User::hasPermission). */
    public const ROLE_DEFAULTS = [
        'manager' => [
            'dashboard.view', 'dashboard.financial', 'remote.view',
            'categories.manage', 'products.view', 'products.manage', 'products.import', 'products.view_cost',
            'sales.create', 'sales.view', 'sales.view_own', 'sales.discount', 'sales.credit', 'sales.cancel', 'sales.reprint',
            'returns.create', 'returns.authorize',
            'customers.view', 'customers.manage', 'customers.settle',
            'stock.view', 'stock.adjust', 'suppliers.manage', 'purchases.view', 'purchases.create', 'purchases.validate',
            'inventories.view', 'inventories.count', 'inventories.validate',
            'cash.use', 'cash.view_all', 'cash.close_any', 'expenses.view', 'expenses.create', 'expenses.approve',
            'reports.view', 'reports.financial', 'reports.export', 'sync.resolve',
        ],
        'cashier' => [
            'dashboard.view', 'products.view', 'sales.create', 'sales.view_own', 'returns.create',
            'customers.view', 'customers.manage', 'customers.settle', 'cash.use', 'expenses.create',
        ],
        'storekeeper' => [
            'dashboard.view', 'categories.manage', 'products.view', 'products.manage', 'products.import', 'products.view_cost',
            'stock.view', 'stock.adjust', 'suppliers.manage', 'purchases.view', 'purchases.create',
            'inventories.view', 'inventories.count',
        ],
    ];

    /** @return array<string, string> */
    public static function flat(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }
}
