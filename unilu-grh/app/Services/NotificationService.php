<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\AgentNotification;
use Illuminate\Support\Facades\Log;

/**
 * Notifie l'agent concerné : dans l'application s'il a un compte, par courriel si une adresse est connue.
 * Un échec d'envoi de courriel ne bloque jamais l'opération métier (il est journalisé).
 */
class NotificationService
{
    public const EVENTS = [
        'nomination' => 'Nouvelle nomination', 'promotion' => 'Promotion', 'grade_change' => 'Changement de grade',
        'assignment' => 'Nouvelle affectation', 'mutation' => 'Mutation', 'leave_approved' => 'Validation d’un congé',
        'availability' => 'Mise en disponibilité', 'suspension' => 'Suspension', 'reinstatement' => 'Réintégration',
        'prime_paid' => 'Paiement d’une prime', 'advance_paid' => 'Versement d’un acompte', 'support_granted' => 'Attribution d’un soutien social',
        'mandate_due' => 'Échéance d’un mandat', 'situation_due' => 'Échéance d’un congé ou d’une situation', 'correction' => 'Demande de correction',
    ];

    public function toEmployee(Employee $employee, string $event, string $body, ?string $url = null): void
    {
        $title = self::EVENTS[$event] ?? $event;
        $user = $employee->user;
        $this->send($user ?: $employee, new AgentNotification($event, $title, $body, $url));
        // Si l'agent a un compte sans adresse mais que le dossier en a une, le courriel part vers le dossier.
        if ($user && ! $user->email && $employee->email && config('grh.mail_notifications')) {
            $this->send($employee, new AgentNotification($event, $title, $body, $url));
        }
    }

    /** Notifie les utilisateurs détenant une permission (ex. gestionnaires RH pour une échéance). */
    public function toHolders(string $permission, string $event, string $body, ?string $url = null, ?Employee $about = null): void
    {
        $title = self::EVENTS[$event] ?? $event;
        User::query()->where('is_active', true)->whereNull('archived_at')->get()
            ->filter(fn (User $u) => $about ? $u->canOnEmployee($permission, $about) : $u->hasPermission($permission))
            ->each(fn (User $u) => $this->send($u, new AgentNotification($event, $title, $body, $url)));
    }

    private function send(object $notifiable, AgentNotification $n): void
    {
        try {
            $notifiable->notify($n);
        } catch (\Throwable $e) {
            Log::warning('Notification non envoyée : '.$e->getMessage());
            if ($notifiable instanceof User) {
                // Le canal interne doit au moins aboutir.
                try {
                    $notifiable->notifyNow($n, ['database']);
                } catch (\Throwable) {
                }
            }
        }
    }
}
