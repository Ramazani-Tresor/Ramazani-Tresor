<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\BankAccount;
use App\Models\Device;
use App\Models\Payment;
use App\Models\Student;
use App\Models\SyncOperation;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * Synchronisation des saisies préparées hors connexion (déclarations de bordereaux).
 * Le serveur de référence est l'unique autorité d'écriture :
 *  - clé d'idempotence = UUID généré sur le poste ; une opération déjà traitée renvoie son résultat ;
 *  - droits, périmètre, année (ouverte/clôturée), étudiant, compte et règles financières sont RECONTRÔLÉS ;
 *  - une saisie acceptée devient un paiement « soumis au contrôle » : aucune validation financière hors serveur ;
 *  - référence bancaire déjà utilisée → « en conflit » (décision humaine), jamais « la dernière modification gagne ».
 */
class SyncService
{
    public function __construct(private PaymentService $payments) {}

    /** @return array{status:string, messages:array, payment_number:?string} */
    public function pushPayment(array $payload, User $user, string $deviceId): array
    {
        $uuid = (string) ($payload['client_uuid'] ?? '');
        $this->touchDevice($deviceId, $user, true);
        if ($payment = Payment::query()->where('client_uuid', $uuid)->first()) {
            return ['status' => 'accepted', 'messages' => ['Déjà enregistrée (aucun doublon)'], 'payment_number' => $payment->number];
        }
        $op = SyncOperation::query()->where('client_uuid', $uuid)->first();
        if ($op && $op->status !== 'conflict') {
            return ['status' => $op->status, 'messages' => $op->messages ?? [], 'payment_number' => null];
        }
        if ($op && $op->status === 'conflict' && ! $op->resolved_at) {
            $op->increment('attempts');

            return ['status' => 'conflict', 'messages' => $op->messages ?? [], 'payment_number' => null];
        }

        [$status, $messages, $payment] = $this->process($payload, $user, $deviceId, false);
        $attrs = ['type' => 'payment_declaration', 'device_id' => $deviceId, 'user_id' => $user->id, 'payload' => $this->stripProof($payload),
            'status' => $status, 'messages' => $messages, 'prepared_at' => $payload['prepared_at'] ?? null,
            'result_type' => $payment?->getMorphClass(), 'result_id' => $payment?->id];
        $op ? $op->update($attrs + ['attempts' => $op->attempts + 1]) : SyncOperation::create($attrs + ['client_uuid' => $uuid]);
        AuditService::log('sync.push', $payment, "Synchronisation {$uuid} depuis {$deviceId} : {$status}", null, ['messages' => $messages]);

        return ['status' => $status, 'messages' => $messages, 'payment_number' => $payment?->number];
    }

    /** Décision humaine sur un conflit : accepter (création malgré l'anomalie, qui restera visible) ou rejeter. */
    public function resolve(SyncOperation $op, string $decision, User $user, string $note): SyncOperation
    {
        if ($op->status !== 'conflict' || $op->resolved_at) {
            throw new BusinessException('Cette opération n’est pas en conflit.');
        }
        if (trim($note) === '') {
            throw new BusinessException('Un commentaire est obligatoire.');
        }
        if ($decision === 'reject') {
            $op->update(['status' => 'rejected', 'resolved_by' => $user->id, 'resolved_at' => now(), 'resolution_note' => $note]);
        } else {
            [$status, $messages, $payment] = $this->process($op->payload, $op->user, $op->device_id, true);
            if ($status !== 'accepted') {
                throw new BusinessException('Acceptation impossible : '.implode(' ; ', $messages));
            }
            $op->update(['status' => 'accepted', 'messages' => $messages, 'result_type' => $payment->getMorphClass(), 'result_id' => $payment->id,
                'resolved_by' => $user->id, 'resolved_at' => now(), 'resolution_note' => $note]);
        }
        AuditService::log('sync.resolve', $op, "Conflit {$op->client_uuid} : {$decision}", null, null, $note);

        return $op;
    }

    private function process(array $payload, User $user, string $deviceId, bool $forceConflicts): array
    {
        try {
            if (! Settings::bool('offline_enabled')) {
                throw new BusinessException('La saisie hors connexion est désactivée.');
            }
            if (! $user->is_active || ! $user->hasPermission('payments.create') || ! $user->hasPermission('sync.use')) {
                throw new BusinessException('Droits insuffisants au moment de la synchronisation (droits révoqués ou modifiés).');
            }
            $student = Student::query()->where('matricule', $payload['matricule'] ?? '')->first();
            if (! $student) {
                throw new BusinessException('Étudiant introuvable (matricule '.($payload['matricule'] ?? '?').').');
            }
            $account = BankAccount::query()->find($payload['bank_account_id'] ?? 0);
            if (! $account) {
                throw new BusinessException('Compte bénéficiaire inconnu.');
            }
            $ref = PaymentService::normalizeReference($payload['bank_reference'] ?? null);
            if ($ref && ! $forceConflicts) {
                $dup = Payment::query()->where('bank_account_id', $account->id)->where('bank_reference_normalized', $ref)->whereNotIn('status', ['rejected', 'reversed'])->first();
                if ($dup) {
                    return ['conflict', ["Référence bancaire déjà enregistrée sur le paiement {$dup->number} : décision d'un responsable requise."], null];
                }
            }
            $proof = $this->storeProof($payload);
            [$payment] = $this->payments->submit([
                'client_uuid' => $payload['client_uuid'],
                'student_id' => $student->id,
                'academic_year_id' => (int) ($payload['academic_year_id'] ?? 0),
                'bank_account_id' => $account->id,
                'bank_reference' => $payload['bank_reference'] ?? null,
                'deposit_date' => $payload['deposit_date'] ?? '',
                'amount' => $payload['amount'] ?? '0',
                'currency' => $payload['currency'] ?? $account->currency,
                'depositor_name' => $payload['depositor_name'] ?? null,
            ], $payload['allocations'] ?? [], $proof, $user, 'offline_sync', $deviceId);

            return ['accepted', ['Saisie acceptée : paiement '.$payment->number.' soumis au contrôle (non validé).'], $payment];
        } catch (BusinessException $e) {
            return ['rejected', [$e->getMessage()], null];
        }
    }

    private function storeProof(array $payload): ?array
    {
        if (empty($payload['proof_base64'])) {
            return null;
        }
        $bin = base64_decode((string) $payload['proof_base64'], true);
        $maxKb = Settings::int('offline_max_payload_kb');
        if ($bin === false || strlen($bin) > $maxKb * 1024) {
            throw new BusinessException("Justificatif invalide ou trop volumineux (maximum {$maxKb} Ko).");
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bin);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if (! $ext) {
            throw new BusinessException('Type de justificatif non autorisé (PDF, JPG ou PNG).');
        }
        $sha = hash('sha256', $bin);
        $path = 'proofs/'.date('Y/m').'/'.$sha.'.'.$ext;
        Storage::put($path, $bin);

        return ['path' => $path, 'sha256' => $sha];
    }

    private function stripProof(array $payload): array
    {
        unset($payload['proof_base64']);

        return $payload;
    }

    public function touchDevice(string $deviceId, User $user, bool $sync = false): void
    {
        $d = Device::query()->firstOrNew(['device_id' => mb_substr($deviceId, 0, 64)]);
        $d->user_id = $user->id;
        $d->user_agent = mb_substr((string) request()->userAgent(), 0, 255);
        $d->last_seen_at = now();
        if ($sync) {
            $d->last_sync_at = now();
        }
        $d->save();
    }
}
