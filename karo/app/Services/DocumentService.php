<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\IssuedDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\Labels;
use Illuminate\Database\Eloquent\Model;

/**
 * Registre des documents : numéro unique, date, émetteur, statut, empreinte du contenu et jeton
 * de vérification (QR code). La page de vérification n'affiche que des données minimales
 * (type, numéro, date, statut, initiales, montant) : aucune donnée personnelle détaillée.
 */
class DocumentService
{
    public function __construct(private NumberingService $numbering) {}

    public function issue(string $type, Student $student, ?Model $subject, array $summary, User $user, string $status = 'final', array $content = []): IssuedDocument
    {
        if (! array_key_exists($type, Labels::STATUSES['document_type'])) {
            throw new BusinessException('Type de document inconnu.');
        }
        $summary = ['student_initials' => $student->maskedName()] + $summary;
        $doc = IssuedDocument::create([
            'number' => $this->numbering->next($type),
            'type' => $type,
            'student_id' => $student->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'status' => $status,
            'verification_token' => bin2hex(random_bytes(20)),
            'content_hash' => hash('sha256', json_encode([$summary, $content], JSON_UNESCAPED_UNICODE)),
            'summary' => $summary,
            'content' => $content,
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]);
        AuditService::log('document.issue', $doc, "Émission {$doc->number} ({$type}, {$status})");

        return $doc;
    }

    public function verificationUrl(IssuedDocument $doc): string
    {
        return route('documents.verify', $doc->verification_token);
    }
}
