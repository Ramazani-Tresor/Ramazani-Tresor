<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\OrgUnit;

/** Libellés français des statuts et listes de valeurs, et pastilles de statut. */
final class Labels
{
    public const STATUSES = [
        'employee' => Employee::STATUSES,
        'family' => PermissionRegistry::FAMILIES,
        'org_type' => OrgUnit::TYPES,
        'sex' => ['M' => 'Masculin', 'F' => 'Féminin'],
        'marital' => ['single' => 'Célibataire', 'married' => 'Marié(e)', 'divorced' => 'Divorcé(e)', 'widowed' => 'Veuf / veuve'],
        'track' => ['teaching' => 'Assistant d’enseignement', 'research' => 'Assistant de recherche'],
        'decision' => ['draft' => 'À valider', 'validated' => 'Validée', 'rejected' => 'Rejetée', 'cancelled' => 'Annulée'],
        'career_type' => ['engagement' => 'Engagement', 'nomination' => 'Nomination', 'promotion' => 'Promotion', 'mandate_renewal' => 'Renouvellement de mandat', 'reclassification' => 'Reclassement', 'doctorate' => 'Obtention du doctorat', 'function_change' => 'Changement de fonction'],
        'qualification' => ['licence' => 'Licence', 'master' => 'Master', 'dea' => 'Diplôme d’études approfondies (DEA)', 'research_thesis' => 'Mémoire de recherche', 'dea_thesis' => 'Mémoire de DEA', 'doctoral_thesis' => 'Thèse de doctorat', 'doctorate' => 'Grade académique de docteur', 'other' => 'Autre diplôme ou qualification'],
        'assignment' => ['active' => 'En cours', 'closed' => 'Clôturée'],
        'mutation_kind' => ['internal' => 'Mutation interne', 'external' => 'Mutation externe'],
        'situation' => ['requested' => 'Demandée', 'approved' => 'Approuvée (en cours)', 'rejected' => 'Rejetée', 'closed' => 'Clôturée', 'cancelled' => 'Annulée'],
        'situation_kind' => ['leave' => 'Congé', 'suspension' => 'Suspension', 'availability' => 'Disponibilité', 'reinstatement' => 'Réintégration', 'end' => 'Fin de fonctions'],
        'entitlement' => ['due' => 'Dû', 'suspended' => 'Suspendu', 'cancelled' => 'Annulé'],
        'payment_kind' => ['advance' => 'Acompte', 'payment' => 'Paiement', 'regularization' => 'Régularisation', 'reversal' => 'Contrepassation'],
        'payment' => ['pending' => 'À valider', 'validated' => 'Validé', 'rejected' => 'Rejeté', 'reversed' => 'Contrepassé'],
        'payment_mode' => ['bank_transfer' => 'Virement bancaire', 'cash' => 'Espèces', 'mobile_money' => 'Monnaie électronique', 'check' => 'Chèque', 'offset' => 'Compensation / retenue'],
        'periodicity' => ['monthly' => 'Mensuelle', 'quarterly' => 'Trimestrielle', 'semester' => 'Semestrielle', 'yearly' => 'Annuelle', 'tranche' => 'Par tranche', 'one_off' => 'Ponctuelle'],
        'support' => ['requested' => 'Demandé', 'approved' => 'Approuvé', 'rejected' => 'Rejeté', 'paid' => 'Payé'],
        'candidate' => ['submitted' => 'Déposée', 'under_review' => 'En examen', 'validated' => 'Validée', 'rejected' => 'Rejetée', 'engaged' => 'Engagé'],
        'asset_kind' => ['immovable' => 'Immobilier', 'movable' => 'Mobilier et équipement'],
        'asset_condition' => ['new' => 'Neuf', 'good' => 'Bon', 'fair' => 'Moyen', 'poor' => 'Mauvais', 'out_of_order' => 'Hors d’usage'],
        'asset_status' => ['in_service' => 'En service', 'reformed' => 'Réformé', 'scrapped' => 'Mis au rebut'],
        'asset_movement' => ['entry' => 'Entrée en patrimoine', 'assignment' => 'Affectation', 'transfer' => 'Transfert', 'condition' => 'Changement d’état', 'reform' => 'Réforme', 'scrap' => 'Mise au rebut', 'inventory' => 'Constat d’inventaire'],
        'acquisition' => ['purchase' => 'Achat', 'construction' => 'Construction', 'donation' => 'Don', 'transfer' => 'Transfert', 'other' => 'Autre'],
        'inventory' => ['open' => 'En cours', 'closed' => 'Clôturé'],
        'correction' => ['pending' => 'En attente', 'accepted' => 'Acceptée', 'rejected' => 'Rejetée'],
        'year' => ['planned' => 'Planifiée', 'open' => 'Ouverte', 'closed' => 'Clôturée'],
    ];

    public const BADGES = [
        'active' => 'ok', 'validated' => 'ok', 'approved' => 'ok', 'paid' => 'ok', 'accepted' => 'ok', 'engaged' => 'ok', 'due' => 'ok', 'in_service' => 'ok', 'open' => 'ok',
        'draft' => 'warn', 'pending' => 'warn', 'requested' => 'warn', 'submitted' => 'warn', 'under_review' => 'info', 'on_leave' => 'info', 'available' => 'info',
        'suspended' => 'danger', 'rejected' => 'danger', 'deceased' => 'muted', 'transferred_out' => 'muted', 'retired' => 'muted', 'resigned' => 'muted', 'departed' => 'muted',
        'closed' => 'muted', 'cancelled' => 'muted', 'reversed' => 'muted', 'reformed' => 'warn', 'scrapped' => 'muted', 'planned' => 'info',
    ];

    public static function status(string $group, ?string $status): string
    {
        return self::STATUSES[$group][$status] ?? (string) $status;
    }

    public static function options(string $group): array
    {
        return self::STATUSES[$group] ?? [];
    }

    public static function badge(string $group, ?string $status): string
    {
        $cls = self::BADGES[$status] ?? 'muted';

        return '<span class="badge-st st-'.$cls.'">'.e(self::status($group, $status)).'</span>';
    }
}
