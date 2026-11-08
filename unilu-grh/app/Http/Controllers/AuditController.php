<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::query()->with('user')->orderByDesc('id');
        if ($request->input('kind') === 'auth') {
            $q->where('action', 'like', 'auth.%');
        }
        if ($request->filled('action')) {
            $q->where('action', 'like', $request->input('action').'%');
        }
        if ($request->filled('user')) {
            $q->where('user_id', $request->integer('user'));
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        return view('audit.index', ['logs' => $q->paginate(50)->withQueryString(), 'chain' => AuditService::verifyChain(), 'users' => User::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function backups(BackupService $backups)
    {
        return view('audit.backups', ['files' => $backups->list(), 'configured' => (bool) config('grh.backup.key'), 'secondary' => config('grh.backup.secondary_path')]);
    }

    public function createBackup(BackupService $backups)
    {
        $r = $backups->create();

        return back()->with('success', "Sauvegarde chiffrée {$r['name']} créée (".count($r['copies']).' copie(s)).');
    }

    public function downloadBackup(string $file, BackupService $backups)
    {
        AuditService::log('backup.download', null, "Téléchargement de la sauvegarde {$file}");

        return response()->download($backups->path($file));
    }
}
