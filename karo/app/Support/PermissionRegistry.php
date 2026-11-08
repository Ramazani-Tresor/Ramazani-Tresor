<?php

namespace App\Support;

/**
 * Permissions granulaires par module et action. L'attribution se fait par rôle,
 * avec un périmètre facultatif (faculté, année académique) au niveau de l'affectation.
 * Les permissions « sensibles » sont des approbations financières : elles ne sont
 * jamais accordées au rôle d'administration technique.
 */
final class PermissionRegistry
{
    /** module => [slug => [libellé, sensible]] */
    public const PERMISSIONS = [
        'Tableau de bord et rapports' => [
            'dashboard.view' => ['Voir le tableau de bord', false],
            'reports.view' => ['Consulter les rapports', false],
            'reports.export' => ['Exporter les rapports (PDF, Excel)', false],
        ],
        'Étudiants' => [
            'students.view' => ['Consulter les dossiers étudiants', false],
            'students.manage' => ['Créer et modifier les dossiers et inscriptions', false],
            'students.import' => ['Importer des étudiants (Excel)', false],
        ],
        'Frais et créances' => [
            'fees.view' => ['Consulter frais, barèmes et tranches', false],
            'fees.manage' => ['Préparer frais, barèmes et tranches (brouillons)', false],
            'fees.approve' => ['Approuver et activer les barèmes', true],
            'charges.generate' => ['Générer les créances', false],
            'charges.manage' => ['Créer ou annuler une créance manuelle', false],
            'adjustments.request' => ['Demander bourse, réduction, exonération, ajustement', false],
            'adjustments.approve' => ['Approuver bourses, réductions et ajustements', true],
        ],
        'Paiements' => [
            'payments.view' => ['Consulter les paiements', false],
            'payments.create' => ['Saisir les bordereaux', false],
            'payments.check' => ['Contrôler les justificatifs', false],
            'payments.validate' => ['Valider les paiements (affectation effective)', true],
            'payments.reverse' => ['Contrepasser un paiement validé', true],
            'credits.request' => ['Demander compensation, remboursement ou report', false],
            'credits.approve' => ['Approuver l’utilisation des excédents', true],
        ],
        'Banque et rapprochement' => [
            'bank.import' => ['Importer les relevés bancaires', false],
            'bank.review' => ['Vérifier les lignes de relevé incertaines', false],
            'bank.reconcile' => ['Rapprocher relevés et paiements', true],
        ],
        'Contrats et documents' => [
            'contracts.view' => ['Consulter les contrats', false],
            'contracts.manage' => ['Préparer les contrats et nouvelles versions', false],
            'contracts.approve' => ['Approuver et archiver les contrats signés', true],
            'documents.issue' => ['Émettre reçus, relevés et attestations', false],
        ],
        'Examens' => [
            'exams.view' => ['Consulter le contrôle financier des examens', false],
            'exams.manage' => ['Paramétrer les conditions des sessions', false],
            'exams.waive' => ['Enregistrer une dérogation exceptionnelle', true],
        ],
        'Années académiques' => [
            'years.manage' => ['Créer et ouvrir les années, sessions et semestres', false],
            'years.close' => ['Clôturer une année académique', true],
            'years.reopen' => ['Rouvrir une année clôturée (exceptionnel)', true],
        ],
        'Paramètres' => [
            'structure.manage' => ['Gérer facultés, départements, filières, promotions', false],
            'finance_settings.manage' => ['Gérer banques, comptes, devises', false],
            'rates.approve' => ['Approuver un taux de conversion', true],
            'settings.manage' => ['Paramètres institutionnels et numérotation', false],
        ],
        'Administration technique' => [
            'users.manage' => ['Gérer les utilisateurs', false],
            'roles.manage' => ['Gérer les rôles et permissions', false],
            'backups.manage' => ['Sauvegardes et maintenance', false],
            'audit.view' => ['Consulter le journal d’audit', false],
            'sync.use' => ['Préparer des saisies hors connexion', false],
            'sync.resolve' => ['Traiter les conflits de synchronisation', false],
        ],
        'Portail étudiant' => [
            'student.self' => ['Consulter son propre compte', false],
        ],
    ];

    public const ROLES = [
        'rector' => ['Recteur', 'Consultation globale, tableaux de bord et rapports institutionnels', true, [
            'dashboard.view', 'reports.view', 'reports.export', 'students.view', 'fees.view', 'payments.view',
            'contracts.view', 'exams.view', 'audit.view',
        ]],
        'academic_secretary' => ['Secrétaire général académique', 'Situations utiles aux inscriptions et aux examens', false, [
            'dashboard.view', 'reports.view', 'reports.export', 'students.view', 'students.manage', 'students.import',
            'fees.view', 'exams.view', 'exams.manage', 'years.manage',
        ]],
        'budget_admin' => ['Administrateur de budget', 'Supervision financière et approbations', true, [
            'dashboard.view', 'reports.view', 'reports.export', 'students.view', 'fees.view', 'fees.manage', 'fees.approve',
            'charges.generate', 'charges.manage', 'adjustments.request', 'adjustments.approve', 'payments.view',
            'payments.validate', 'payments.reverse', 'credits.request', 'credits.approve', 'bank.review', 'bank.reconcile',
            'contracts.view', 'contracts.manage', 'contracts.approve', 'documents.issue', 'exams.view', 'exams.waive',
            'years.close', 'years.reopen', 'finance_settings.manage', 'rates.approve', 'settings.manage', 'audit.view', 'sync.resolve',
        ]],
        'entry_clerk' => ['Comptable de saisie', 'Étudiants, bordereaux et préparation des opérations', false, [
            'dashboard.view', 'students.view', 'students.manage', 'students.import', 'fees.view', 'charges.generate',
            'adjustments.request', 'payments.view', 'payments.create', 'credits.request', 'bank.import',
            'contracts.view', 'contracts.manage', 'documents.issue', 'exams.view', 'sync.use',
        ]],
        'validator' => ['Contrôleur / validateur', 'Contrôle des justificatifs et validation', true, [
            'dashboard.view', 'reports.view', 'students.view', 'fees.view', 'payments.view', 'payments.check',
            'payments.validate', 'bank.review', 'bank.reconcile', 'contracts.view', 'documents.issue', 'exams.view', 'sync.resolve',
        ]],
        'tech_admin' => ['Administrateur technique', 'Utilisateurs, paramètres techniques et maintenance (aucune approbation financière)', true, [
            'users.manage', 'roles.manage', 'backups.manage', 'audit.view', 'structure.manage', 'settings.manage', 'years.manage',
        ]],
        'auditor' => ['Auditeur', 'Consultation des opérations et des journaux', false, [
            'dashboard.view', 'reports.view', 'reports.export', 'students.view', 'fees.view', 'payments.view',
            'contracts.view', 'exams.view', 'audit.view',
        ]],
        'student' => ['Étudiant', 'Consultation de son propre compte et de ses documents', false, ['student.self']],
    ];

    /** @return array<string, array{0:string,1:bool}> */
    public static function flat(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    public static function isSensitive(string $slug): bool
    {
        return self::flat()[$slug][1] ?? false;
    }
}
