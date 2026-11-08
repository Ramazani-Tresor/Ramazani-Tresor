<?php

namespace App\Services;

use App\Models\AdministrativeSituation;

/** Alertes quotidiennes : échéances de mandat et de situations administratives (commande grh:alerts). */
class AlertService
{
    public function __construct(private NotificationService $notifier, private CareerService $career, private SituationService $situations) {}

    public function run(): array
    {
        $stats = ['mandates' => 0, 'situations' => 0, 'started' => $this->situations->applyDueStarts()];
        $mDays = (int) config('grh.alert_days_mandate', 90);
        foreach ($this->career->expiringMandates($mDays)->get() as $e) {
            $key = 'mandate_alert_'.$e->id.'_'.$e->mandate_end?->format('Ymd');
            if (cache()->has($key)) {
                continue;
            }
            $msg = "Le mandat « {$e->mandate?->name} » de {$e->fullName()} ({$e->matricule}) ".($e->mandate_end->isPast() ? 'a expiré le ' : 'expire le ').$e->mandate_end->format('d/m/Y').'.';
            $this->notifier->toEmployee($e, 'mandate_due', $msg, route('me.career'));
            $this->notifier->toHolders('career.manage', 'mandate_due', $msg, route('employees.show', $e), $e);
            cache()->put($key, true, now()->addDays(30));
            $stats['mandates']++;
        }
        $sDays = (int) config('grh.alert_days_situation', 7);
        foreach (AdministrativeSituation::query()->with(['employee', 'type'])->where('status', 'approved')->whereNull('alert_sent_at')
            ->whereNotNull('planned_end_date')->whereDate('planned_end_date', '<=', now()->addDays($sDays))->get() as $s) {
            $msg = "{$s->type->name} de {$s->employee->fullName()} : échéance le ".$s->planned_end_date->format('d/m/Y').' (prolongation, clôture ou réintégration à décider).';
            $this->notifier->toEmployee($s->employee, 'situation_due', $msg, route('me.situations'));
            $this->notifier->toHolders('situations.validate', 'situation_due', $msg, route('employees.show', $s->employee), $s->employee);
            $s->update(['alert_sent_at' => now()]);
            $stats['situations']++;
        }

        return $stats;
    }
}
