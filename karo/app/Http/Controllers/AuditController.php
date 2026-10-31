<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->period($request, 7);
        $logs = $this->query($request, $from, $to)->latest('id')->paginate(50)->withQueryString();

        return view('audit.index', ['logs' => $logs, 'from' => $from, 'to' => $to, 'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')]);
    }

    public function verify()
    {
        $r = AuditService::verifyChain();
        AuditService::log('audit.verify', null, $r['ok'] ? "Chaîne d’audit vérifiée : {$r['checked']} entrées intègres" : "ALERTE : chaîne d’audit rompue à l’entrée #{$r['broken_at']}");

        return back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? "Journal intègre : {$r['checked']} entrées vérifiées." : "Altération détectée à l’entrée #{$r['broken_at']} : le journal a été modifié hors de l’application.");
    }

    /** Copie indépendante (CSV signé par empreinte) à conserver hors du serveur. */
    public function export(Request $request)
    {
        [$from, $to] = $this->period($request, 30);
        AuditService::log('audit.export', null, 'Export du journal du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y'));
        $logs = $this->query($request, $from, $to)->orderBy('id')->get();

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'date', 'utilisateur', 'action', 'objet', 'description', 'motif', 'avant', 'après', 'ip', 'poste', 'empreinte_precedente', 'empreinte'], ';');
            foreach ($logs as $l) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v, [$l->id, $l->created_at, $l->user?->name, $l->action, $l->auditable_type ? class_basename($l->auditable_type).'#'.$l->auditable_id : '', $l->description, $l->reason,
                    json_encode($l->old_values, JSON_UNESCAPED_UNICODE), json_encode($l->new_values, JSON_UNESCAPED_UNICODE), $l->ip_address, $l->device_id, $l->previous_hash, $l->hash]), ';');
            }
            fclose($out);
        }, 'journal-audit-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request, $from, $to)
    {
        return AuditLog::query()->with('user')->whereBetween('created_at', [$from, $to])
            ->when($request->integer('user'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->input('action'), fn ($q, $v) => $q->where('action', $v))
            ->when($request->input('q'), fn ($q, $t) => $q->where('description', 'like', "%{$t}%"));
    }
}
