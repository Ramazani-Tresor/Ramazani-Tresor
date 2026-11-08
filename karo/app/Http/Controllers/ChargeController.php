<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Services\ChargeService;
use App\Support\Authz;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function __construct(private ChargeService $charges) {}

    public function generateForStudent(Request $request, Student $student)
    {
        $this->authorizeStudent($student, 'charges.generate');
        $e = $student->enrollmentFor($request->integer('year'));
        abort_unless($e, 404);
        $r = $this->charges->generateForEnrollment($e, $request->user());

        return back()->with($r['missing'] ? 'warning' : 'success', "Créances : {$r['created']} créée(s), {$r['existing']} déjà existante(s).".($r['missing'] ? ' Sans barème applicable : '.implode(', ', $r['missing']).'.' : ''));
    }

    public function generateBulk(Request $request)
    {
        $data = $request->validate(['year' => 'required|exists:academic_years,id', 'faculty' => 'nullable|exists:faculties,id', 'promotion' => 'nullable|exists:promotions,id']);
        $scope = $request->user()->facultyScope('charges.generate');
        if ($scope !== null && (empty($data['faculty']) || ! in_array((int) $data['faculty'], $scope, true))) {
            abort(403);
        }
        $r = $this->charges->generateBulk((int) $data['year'], $data['faculty'] ?? null, $data['promotion'] ?? null, $request->user());

        return back()->with($r['missing'] ? 'warning' : 'success', "Génération terminée : {$r['created']} créance(s) créée(s), {$r['existing']} déjà existante(s) (aucun doublon).".($r['missing'] ? ' Barème manquant : '.implode(', ', array_slice($r['missing'], 0, 8)).'.' : ''));
    }

    public function storeManual(Request $request, Student $student)
    {
        $this->authorizeStudent($student, 'charges.manage');
        $d = $request->validate(['academic_year_id' => 'required|exists:academic_years,id', 'fee_category_id' => 'required|exists:fee_categories,id',
            'amount' => 'required|numeric|gt:0', 'currency' => 'required|exists:currencies,code', 'due_date' => 'required|date', 'reason' => 'required|string|max:255', 'client_uuid' => 'required|uuid']);
        $this->charges->createManual($student, (int) $d['academic_year_id'], FeeCategory::findOrFail($d['fee_category_id']), (string) $d['amount'], $d['currency'], $d['due_date'], $d['reason'], $request->user(), $d['client_uuid']);

        return back()->with('success', 'Créance manuelle créée.');
    }

    public function cancel(Request $request, Charge $charge)
    {
        $this->authorizeStudent($charge->student, 'charges.manage');
        Authz::need($request->user(), 'charges.manage');
        $this->charges->cancel($charge, (string) $request->validate(['reason' => 'required|string|max:255'])['reason'], $request->user());

        return back()->with('success', 'Créance annulée (conservée dans l’historique).');
    }
}
