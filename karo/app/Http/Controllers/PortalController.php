<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Services\BalanceService;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Portail étudiant (module activable) : consultation de son propre compte uniquement. */
class PortalController extends Controller
{
    public function show(Request $request, BalanceService $balances)
    {
        abort_unless(Settings::bool('student_portal_enabled'), 404, 'Module non activé.');
        $student = $request->user()->student;
        abort_unless($student, 403);
        $year = $request->filled('year') ? AcademicYear::find($request->integer('year')) : AcademicYear::current();

        return view('portal.show', ['student' => $student, 'year' => $year, 'situation' => $balances->situation($student, $year?->id),
            'years' => AcademicYear::query()->whereIn('id', $student->enrollments()->pluck('academic_year_id'))->orderByDesc('starts_on')->get(),
            'docs' => IssuedDocument::query()->where('student_id', $student->id)->latest('id')->get()]);
    }
}
