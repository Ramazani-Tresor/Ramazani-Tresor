<?php

namespace App\Support;

final class Labels
{
    public const STATUSES = [
        'payment' => ['submitted' => 'Soumis au contrôle', 'checked' => 'Justificatif contrôlé', 'validated' => 'Validé', 'rejected' => 'Rejeté', 'reversed' => 'Contrepassé'],
        'bank' => ['unconfirmed' => 'Non confirmé par la banque', 'partial' => 'Partiellement confirmé', 'confirmed' => 'Confirmé par la banque', 'not_applicable' => 'Sans objet (report)'],
        'allocation' => ['proposed' => 'Proposée', 'active' => 'Effective', 'reversed' => 'Annulée'],
        'adjustment' => ['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté', 'reversed' => 'Annulé'],
        'adjustment_type' => ['scholarship' => 'Bourse', 'reduction' => 'Réduction', 'exemption' => 'Exonération', 'sponsorship' => 'Prise en charge', 'increase' => 'Ajustement à la hausse', 'decrease' => 'Ajustement à la baisse', 'transfer_out' => 'Report vers l’année suivante', 'schedule_revision' => 'Révision de barème'],
        'credit' => ['pending' => 'En attente d’approbation', 'executed' => 'Exécutée', 'rejected' => 'Rejetée'],
        'credit_type' => ['compensation' => 'Compensation', 'refund' => 'Remboursement', 'carry_forward' => 'Report sur l’année suivante'],
        'schedule' => ['draft' => 'Brouillon', 'active' => 'Actif', 'superseded' => 'Remplacé', 'rejected' => 'Rejeté'],
        'year' => ['planned' => 'Planifiée', 'open' => 'Ouverte', 'closed' => 'Clôturée'],
        'contract' => ['draft' => 'Brouillon', 'active' => 'Approuvé', 'signed' => 'Signé (archivé)', 'superseded' => 'Remplacé', 'cancelled' => 'Annulé'],
        'line_match' => ['unmatched' => 'Non rapprochée', 'partial' => 'Partiellement rapprochée', 'matched' => 'Rapprochée', 'ignored' => 'Écartée'],
        'line_review' => ['not_required' => 'Fiable', 'pending' => 'À vérifier', 'verified' => 'Vérifiée', 'rejected' => 'Rejetée'],
        'sync' => ['accepted' => 'Acceptée', 'rejected' => 'Rejetée', 'conflict' => 'En conflit'],
        'enrollment_status' => ['new' => 'Nouvel inscrit', 'returning' => 'Ancien étudiant', 'finalist' => 'Finaliste'],
        'clearance' => ['cleared' => 'En règle', 'partial' => 'Partiellement en règle', 'due' => 'Solde dû', 'waiver' => 'Autorisation exceptionnelle'],
        'document' => ['provisional' => 'Provisoire', 'final' => 'Définitif', 'cancelled' => 'Annulé'],
        'document_type' => ['receipt' => 'Reçu de paiement', 'statement' => 'Relevé financier', 'attestation' => 'Attestation de paiement', 'situation' => 'Situation individuelle', 'contract' => 'Contrat de paiement', 'refund' => 'Document de remboursement', 'compensation' => 'Document de compensation', 'carry_forward' => 'Document de report'],
    ];

    public const BADGES = [
        'submitted' => 'warn', 'checked' => 'info', 'validated' => 'ok', 'rejected' => 'danger', 'reversed' => 'muted',
        'unconfirmed' => 'warn', 'partial' => 'info', 'confirmed' => 'ok', 'not_applicable' => 'muted',
        'pending' => 'warn', 'approved' => 'ok', 'executed' => 'ok', 'draft' => 'muted', 'active' => 'ok', 'superseded' => 'muted',
        'open' => 'ok', 'closed' => 'muted', 'planned' => 'info', 'signed' => 'ok', 'cancelled' => 'muted', 'unmatched' => 'warn',
        'matched' => 'ok', 'ignored' => 'muted', 'verified' => 'ok', 'not_required' => 'ok', 'accepted' => 'ok', 'conflict' => 'danger',
        'cleared' => 'ok', 'due' => 'danger', 'waiver' => 'info', 'provisional' => 'warn', 'final' => 'ok', 'proposed' => 'info',
    ];

    public static function status(string $group, ?string $status): string
    {
        return self::STATUSES[$group][$status] ?? (string) $status;
    }

    public static function badge(string $group, ?string $status): string
    {
        $cls = self::BADGES[$status] ?? 'muted';

        return '<span class="badge-st st-'.$cls.'">'.e(self::status($group, $status)).'</span>';
    }
}
