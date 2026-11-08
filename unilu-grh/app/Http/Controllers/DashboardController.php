<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\OrgUnit;
use App\Models\StaffCategory;
use App\Services\CareerService;
use App\Services\StatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function home(Request $request)
    {
        $u = $request->user();
        if ($u->hasPermission('dashboard.view')) {
            return redirect()->route('dashboard');
        }
        if ($u->employee_id && $u->hasPermission('self.view')) {
            return redirect()->route('me.show');
        }
        foreach (['assets.index' => 'assets.view', 'primes.index' => 'primes.view', 'employees.index' => 'employees.directory', 'users.index' => 'users.manage'] as $route => $perm) {
            if ($u->hasPermission($perm)) {
                return redirect()->route($route);
            }
        }

        return view('home-empty');
    }

    private function filters(Request $request): array
    {
        return array_filter($request->only(['year', 'unit', 'family', 'category', 'status']), fn ($v) => $v !== null && $v !== '');
    }

    public function index(Request $request, StatsService $stats, CareerService $career)
    {
        $f = $this->filters($request);
        $u = $request->user();

        return view('dashboard', [
            'f' => $f,
            's' => $stats->dashboard($u, $f),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'units' => OrgUnit::query()->whereIn('type', ['faculty', 'admin_service', 'technical_service', 'rectorate', 'sga', 'sgad', 'vice_rectorate', 'library', 'research_center'])->orderBy('name')->get(),
            'categories' => StaffCategory::query()->orderBy('sort_order')->get(),
            'mandates' => $u->hasPermission('career.manage') || $u->hasPermission('career.validate') ? $career->expiringMandates((int) config('grh.alert_days_mandate'))->get()->filter(fn ($e) => $u->canOnEmployee('employees.view', $e))->take(8) : collect(),
        ]);
    }

    public function scientific(Request $request, StatsService $stats)
    {
        $f = $this->filters($request);

        return view('stats.scientific', [
            'f' => $f,
            's' => $stats->scientific($request->user(), $f),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'units' => OrgUnit::query()->whereIn('type', ['faculty', 'department', 'research_center'])->orderBy('name')->get(),
        ]);
    }
}
