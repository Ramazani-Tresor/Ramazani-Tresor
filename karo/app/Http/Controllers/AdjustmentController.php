<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\ChargeAdjustment;
use App\Services\AdjustmentService;
use App\Support\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdjustmentController extends Controller
{
    public function __construct(private AdjustmentService $svc) {}

    public function index(Request $request)
    {
        $filters = Filters::from($request, $request->user()->hasPermission('adjustments.approve') ? 'adjustments.approve' : 'adjustments.request');
        $status = $request->input('status', 'pending');
        $items = ChargeAdjustment::query()->with(['charge.student', 'charge.category', 'requester', 'approver'])
            ->whereNotIn('type', ['transfer_out'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->whereHas('charge', fn ($c) => $c->where('academic_year_id', $filters->get('year'))
                ->whereHas('student', fn ($s) => $s->inFaculties($filters->facultyScope)))
            ->latest('id')->paginate(25)->withQueryString();

        return view('adjustments.index', compact('items', 'filters', 'status'));
    }

    public function store(Request $request, Charge $charge)
    {
        $this->authorizeStudent($charge->student, 'adjustments.request');
        $d = $request->validate(['type' => 'required|string', 'amount' => 'required|numeric|gt:0', 'reason' => 'required|string|max:255', 'sponsor' => 'nullable|string|max:150', 'justification' => 'nullable|file|max:5120']);
        $path = $request->hasFile('justification') ? $this->storePrivateFile($request->file('justification'), 'justifications')['path'] : null;
        $this->svc->request($charge, $d['type'], (string) $d['amount'], $d['reason'], $request->user(), $d['sponsor'] ?? null, $path);

        return back()->with('success', 'Demande enregistrée : elle doit être approuvée par une autre personne habilitée.');
    }

    public function approve(Request $request, ChargeAdjustment $adjustment)
    {
        $this->authorizeStudent($adjustment->charge->student, 'adjustments.approve');
        $this->svc->approve($adjustment, $request->user(), $request->input('note'));

        return back()->with('success', 'Ajustement approuvé : le solde de la créance est mis à jour.');
    }

    public function reject(Request $request, ChargeAdjustment $adjustment)
    {
        $this->authorizeStudent($adjustment->charge->student, 'adjustments.approve');
        $this->svc->reject($adjustment, $request->user(), (string) $request->validate(['note' => 'required|string|max:255'])['note']);

        return back()->with('success', 'Demande rejetée.');
    }

    public function justification(ChargeAdjustment $adjustment)
    {
        $this->authorizeStudent($adjustment->charge->student, 'students.view');
        abort_unless($adjustment->justification_path && Storage::exists($adjustment->justification_path), 404);

        return Storage::download($adjustment->justification_path, $adjustment->number.'.'.pathinfo($adjustment->justification_path, PATHINFO_EXTENSION));
    }
}
