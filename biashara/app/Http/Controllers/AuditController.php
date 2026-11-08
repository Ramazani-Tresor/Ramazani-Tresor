<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->period($request);
        $logs = AuditLog::query()->with('user')
            ->whereBetween('created_at', [$from, $to])
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->action, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($request->q, fn ($q, $t) => $q->where('description', 'like', "%{$t}%"))
            ->latest('id')->paginate(50)->withQueryString();

        return view('audit.index', [
            'logs' => $logs, 'from' => $from, 'to' => $to,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
