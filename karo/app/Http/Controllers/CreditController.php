<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Charge;
use App\Models\CreditOperation;
use App\Models\Payment;
use App\Services\CreditService;
use App\Services\DocumentService;
use App\Support\Money;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(private CreditService $svc) {}

    public function index(Request $request)
    {
        $scope = $request->user()->facultyScope($request->user()->hasPermission('credits.approve') ? 'credits.approve' : 'credits.request');
        $credits = Payment::query()->with(['student', 'academicYear'])->where('status', 'validated')
            ->whereHas('student', fn ($s) => $s->inFaculties($scope))
            ->when($request->integer('payment'), fn ($q, $v) => $q->whereKey($v))
            ->latest('id')->limit(300)->get()->filter(fn ($p) => Money::isPositive($p->availableAmount()))->values();
        $selected = $request->integer('payment') ? $credits->first() : null;

        return view('credits.index', [
            'credits' => $credits, 'selected' => $selected,
            'targets' => $selected ? Charge::query()->with('category', 'academicYear')->where('student_id', $selected->student_id)->where('status', 'active')->get()->filter(fn ($c) => Money::isPositive($c->balance()))->values() : collect(),
            'nextYear' => $selected ? AcademicYear::query()->where('previous_year_id', $selected->academic_year_id)->first() : null,
            'pending' => CreditOperation::query()->with(['student', 'sourcePayment', 'targetCharge.category', 'targetYear', 'requester'])->where('status', 'pending')->latest('id')->get(),
            'history' => CreditOperation::query()->with(['student', 'sourcePayment', 'approver'])->where('status', '!=', 'pending')->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(Request $request, Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'credits.request');
        $d = $request->validate(['client_uuid' => 'required|uuid', 'type' => 'required|in:compensation,refund,carry_forward', 'amount' => 'required|numeric|gt:0',
            'reason' => 'required|string|max:255', 'target_charge_id' => 'nullable|required_if:type,compensation|integer', 'target_academic_year_id' => 'nullable|required_if:type,carry_forward|integer', 'refund_method' => 'nullable|string|max:80']);
        $op = $this->svc->request($payment, $d['type'], (string) $d['amount'], $d['reason'], $request->user(), $d['client_uuid'], $d['target_charge_id'] ?? null, $d['target_academic_year_id'] ?? null, $d['refund_method'] ?? null);

        return redirect()->route('credits.index')->with('success', "Demande {$op->number} enregistrée : le montant est réservé jusqu’à la décision d’un approbateur.");
    }

    public function show(CreditOperation $operation)
    {
        $this->authorizeStudent($operation->student, 'credits.request');

        return redirect()->route('payments.show', $operation->source_payment_id);
    }

    public function approve(Request $request, CreditOperation $operation, DocumentService $docs)
    {
        $this->authorizeStudent($operation->student, 'credits.approve');
        $op = $this->svc->approve($operation, $request->user(), $request->input('note'), $request->input('refund_reference'));
        $docs->issue($op->type, $op->student, $op, ['operation' => $op->number, 'type' => $op->type, 'amount' => (string) $op->amount, 'currency' => $op->currency, 'source' => $op->sourcePayment->number], $request->user());

        return back()->with('success', "Opération {$op->number} exécutée ; document justificatif émis.");
    }

    public function reject(Request $request, CreditOperation $operation)
    {
        $this->authorizeStudent($operation->student, 'credits.approve');
        $this->svc->reject($operation, $request->user(), (string) $request->validate(['note' => 'required|string|max:255'])['note']);

        return back()->with('success', 'Demande rejetée : le montant redevient disponible.');
    }
}
