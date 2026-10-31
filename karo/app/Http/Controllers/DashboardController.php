<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\StatementLine;
use App\Models\SyncOperation;
use App\Services\MetricsService;
use App\Support\Filters;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function home(Request $request)
    {
        $u = $request->user();
        if ($u->hasPermission('dashboard.view')) {
            return redirect()->route('dashboard');
        }
        if ($u->hasPermission('student.self')) {
            return redirect()->route('portal.show');
        }
        foreach (['payments.entry' => 'payments.create', 'users.index' => 'users.manage', 'settings.index' => 'settings.manage'] as $route => $perm) {
            if ($u->hasPermission($perm)) {
                return redirect()->route($route);
            }
        }

        return view('home-empty');
    }

    public function index(Request $request, MetricsService $metrics)
    {
        $filters = Filters::from($request, 'dashboard.view');
        $f = $filters->toMetrics();

        return view('dashboard', [
            'filters' => $filters,
            'metrics' => $metrics->compute($f),
            'definitions' => MetricsService::DEFINITIONS,
            'breached' => $request->user()->hasPermission('contracts.view') ? $metrics->breachedContracts($f) : null,
            'toReview' => StatementLine::query()->where('review_status', 'pending')->count(),
            'syncConflicts' => SyncOperation::query()->where('status', 'conflict')->count(),
            'anomalies' => Payment::query()->whereNotNull('anomalies')->whereIn('status', ['submitted', 'checked'])->count(),
            'latest' => Payment::query()->with(['student', 'bankAccount.bank'])->where('type', 'bank_deposit')
                ->when($f['year_id'], fn ($q, $v) => $q->where('academic_year_id', $v))
                ->when($f['faculty_ids'] !== null, fn ($q) => $q->whereHas('student', fn ($s) => $s->inFaculties($f['faculty_ids'])))
                ->latest('id')->limit(8)->get(),
        ]);
    }
}
