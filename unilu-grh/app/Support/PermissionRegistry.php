<?php

namespace App\Support;

/**
 * Permissions par module et action, et rôles proposés à l'installation. Les permissions de chaque rôle
 * restent modifiables dans l'écran « Rôles » ; le périmètre (unité, famille de personnel) se règle à l'attribution.
 */
final class PermissionRegistry
{
    /** slug => [libellé, module, sensible] */
    public const PERMISSIONS = [
        'dashboard.view' => ['Consulter le tableau de bord', 'Pilotage', false],
        'reports.view' => ['Éditer les listes et états du personnel', 'Pilotage', false],
        'reports.finance' => ['Éditer les états financiers (primes, acomptes, arriérés, soutiens)', 'Pilotage', true],
        'employees.directory' => ['Annuaire minimal (matricule, nom, unité)', 'Personnel', false],
        'employees.view' => ['Consulter les dossiers du personnel', 'Personnel', false],
        'employees.manage' => ['Créer et modifier les dossiers du personnel', 'Personnel', true],
        'employees.documents' => ['Gérer les pièces numérisées des dossiers', 'Personnel', true],
        'employees.history' => ['Consulter l’historique des modifications', 'Personnel', false],
        'employees.bank' => ['Consulter et modifier les coordonnées bancaires', 'Personnel', true],
        'corrections.handle' => ['Traiter les demandes de correction des agents', 'Personnel', true],
        'career.manage' => ['Préparer nominations et promotions', 'Carrière', true],
        'career.validate' => ['Valider nominations et promotions', 'Carrière', true],
        'qualifications.manage' => ['Gérer diplômes et travaux scientifiques', 'Carrière', false],
        'structure.manage' => ['Gérer la structure organisationnelle et les postes', 'Organisation', true],
        'assignments.manage' => ['Enregistrer les affectations', 'Organisation', true],
        'mutations.manage' => ['Préparer les mutations', 'Mouvements', true],
        'mutations.validate' => ['Valider les mutations', 'Mouvements', true],
        'situations.manage' => ['Enregistrer congés et situations administratives', 'Mouvements', true],
        'situations.validate' => ['Valider congés, suspensions et disponibilités', 'Mouvements', true],
        'deaths.manage' => ['Enregistrer un décès', 'Mouvements', true],
        'deaths.validate' => ['Valider un décès', 'Mouvements', true],
        'deaths.cause' => ['Consulter la cause d’un décès', 'Mouvements', true],
        'recruitment.manage' => ['Gérer les candidatures', 'Recrutement', true],
        'recruitment.engage' => ['Prononcer l’engagement et attribuer le matricule', 'Recrutement', true],
        'primes.view' => ['Consulter les primes et paiements individuels', 'Finances', true],
        'primes.configure' => ['Paramétrer types et taux de primes', 'Finances', true],
        'primes.generate' => ['Générer les droits à primes d’une période', 'Finances', true],
        'payments.create' => ['Saisir paiements, acomptes et régularisations', 'Finances', true],
        'payments.validate' => ['Valider ou contrepasser les paiements', 'Finances', true],
        'social.manage' => ['Enregistrer les demandes de soutien social', 'Social', true],
        'social.approve' => ['Approuver les soutiens sociaux', 'Social', true],
        'social.pay' => ['Enregistrer le paiement des soutiens sociaux', 'Social', true],
        'assets.view' => ['Consulter le patrimoine', 'Patrimoine', false],
        'assets.manage' => ['Gérer les biens et leurs mouvements', 'Patrimoine', true],
        'inventories.manage' => ['Conduire les inventaires', 'Patrimoine', true],
        'settings.manage' => ['Paramétrer les référentiels RH (années, grades, types…)', 'Administration', true],
        'users.manage' => ['Gérer les comptes et les habilitations', 'Administration', true],
        'roles.manage' => ['Modifier les permissions des rôles', 'Administration', true],
        'audit.view' => ['Consulter le journal d’audit et des connexions', 'Administration', true],
        'backups.manage' => ['Gérer les sauvegardes', 'Administration', true],
        'api.tokens' => ['Créer des jetons d’accès à l’API', 'Administration', true],
        'self.view' => ['Accéder à son espace personnel', 'Espace personnel', false],
    ];

    /** slug => [nom, description, permissions] ('*' = toutes) */
    public const ROLES = [
        'super_admin' => ['Super administrateur', 'Toutes les permissions. Réservé à l’administration du système.', ['*']],
        'hr_admin' => ['Administrateur RH', 'Dossiers, carrières, mouvements, paramétrage RH et validations.', [
            'dashboard.view', 'reports.view', 'employees.directory', 'employees.view', 'employees.manage', 'employees.documents', 'employees.history',
            'corrections.handle', 'career.manage', 'career.validate', 'qualifications.manage', 'structure.manage', 'assignments.manage',
            'mutations.manage', 'mutations.validate', 'situations.manage', 'situations.validate', 'deaths.manage', 'deaths.validate', 'deaths.cause',
            'recruitment.manage', 'recruitment.engage', 'social.manage', 'social.approve', 'settings.manage', 'audit.view', 'self.view',
        ]],
        'personnel_manager' => ['Gestionnaire du personnel', 'Tenue des dossiers et préparation des décisions (sans validation).', [
            'dashboard.view', 'reports.view', 'employees.directory', 'employees.view', 'employees.manage', 'employees.documents', 'employees.history',
            'corrections.handle', 'career.manage', 'qualifications.manage', 'assignments.manage', 'mutations.manage', 'situations.manage',
            'deaths.manage', 'recruitment.manage', 'social.manage', 'self.view',
        ]],
        'academic_officer' => ['Responsable du personnel académique', 'À attribuer avec le périmètre « personnel académique ».', [
            'dashboard.view', 'reports.view', 'employees.directory', 'employees.view', 'employees.manage', 'employees.documents', 'employees.history',
            'career.manage', 'qualifications.manage', 'situations.manage', 'self.view',
        ]],
        'scientific_officer' => ['Responsable du personnel scientifique', 'À attribuer avec le périmètre « personnel scientifique ».', [
            'dashboard.view', 'reports.view', 'employees.directory', 'employees.view', 'employees.manage', 'employees.documents', 'employees.history',
            'career.manage', 'qualifications.manage', 'situations.manage', 'self.view',
        ]],
        'finance_manager' => ['Gestionnaire financier', 'Primes, paiements, acomptes, soutiens ; ne peut pas modifier les grades.', [
            'dashboard.view', 'reports.view', 'reports.finance', 'employees.directory', 'employees.view', 'employees.bank',
            'primes.view', 'primes.configure', 'primes.generate', 'payments.create', 'payments.validate', 'social.pay', 'self.view',
        ]],
        'asset_manager' => ['Responsable du patrimoine', 'Biens, mouvements et inventaires, sans données financières individuelles.', [
            'employees.directory', 'assets.view', 'assets.manage', 'inventories.manage', 'self.view',
        ]],
        'faculty_head' => ['Responsable de faculté', 'À attribuer avec le périmètre de la faculté.', [
            'dashboard.view', 'reports.view', 'employees.directory', 'employees.view', 'employees.history', 'situations.manage', 'assets.view', 'self.view',
        ]],
        'service_head' => ['Responsable de service', 'À attribuer avec le périmètre du service.', [
            'dashboard.view', 'reports.view', 'employees.directory', 'employees.view', 'situations.manage', 'assets.view', 'self.view',
        ]],
        'agent' => ['Agent du personnel', 'Espace personnel : consultation de son dossier et demandes de correction.', ['self.view']],
    ];

    public const FAMILIES = ['academic' => 'Personnel académique', 'scientific' => 'Personnel scientifique', 'administrative' => 'Personnel administratif et ouvrier'];

    public static function permissionsFor(string $role): array
    {
        $perms = self::ROLES[$role][2] ?? [];

        return $perms === ['*'] ? array_keys(self::PERMISSIONS) : $perms;
    }
}
