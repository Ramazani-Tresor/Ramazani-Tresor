<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\KinshipDegree;
use App\Models\SocialSupport;
use App\Models\SupportType;
use App\Services\DocumentStore;
use App\Services\SocialSupportService;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $visible = fn ($s) => $u->canOnEmployee('social.manage', $s->employee) || $u->canOnEmployee('social.approve', $s->employee) || $u->hasPermission('social.pay');
        $all = SocialSupport::query()->with(['employee', 'type', 'kinship', 'author', 'approver', 'documents'])->latest()->limit(300)->get()->filter($visible);

        return view('social.index', [
            'requested' => $all->where('status', 'requested'), 'approved' => $all->where('status', 'approved'), 'done' => $all->whereIn('status', ['paid', 'rejected'])->take(60),
            'types' => SupportType::query()->where('is_active', true)->get(), 'kinships' => KinshipDegree::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request, SocialSupportService $svc, DocumentStore $store)
    {
        $data = $request->validate([
            'matricule' => 'required|string|exists:employees,matricule', 'support_type_id' => 'required|exists:support_types,id',
            'kinship_degree_id' => 'nullable|exists:kinship_degrees,id', 'deceased_name' => 'nullable|string|max:150', 'event_date' => 'nullable|date|before_or_equal:today',
            'description' => 'nullable|string|max:1000', 'amount_requested' => 'nullable|numeric|min:0', 'beneficiary' => 'nullable|string|max:150',
        ] + $this->docRules());
        $employee = Employee::query()->where('matricule', $data['matricule'])->firstOrFail();
        $this->authorizeEmployee($employee, 'social.manage');
        $s = $svc->request($employee, $data, $request->user());
        if ($id = $this->attachDocument($request, $employee, 'Pièce justificative — soutien '.$s->number, $s->number, null, 'ATTESTATION')) {
            $s->documents()->attach($id);
        }

        return back()->with('success', "Demande {$s->number} enregistrée.");
    }

    public function approve(Request $request, SocialSupport $support, SocialSupportService $svc)
    {
        $data = $request->validate(['amount_granted' => 'nullable|numeric|min:0.01', 'decision_reference' => 'required|string|max:80']);
        $svc->approve($support, isset($data['amount_granted']) ? (string) $data['amount_granted'] : null, $data['decision_reference'], $request->user());

        return back()->with('success', 'Soutien approuvé.');
    }

    public function reject(Request $request, SocialSupport $support, SocialSupportService $svc)
    {
        $svc->reject($support, $request->validate(['reason' => 'required|string|max:500'])['reason'], $request->user());

        return back()->with('success', 'Demande rejetée.');
    }

    public function pay(Request $request, SocialSupport $support, SocialSupportService $svc)
    {
        $data = $request->validate(['paid_on' => 'required|date|before_or_equal:today', 'payment_reference' => 'required|string|max:80']);
        $svc->pay($support, $data['paid_on'], $data['payment_reference'], $request->user());

        return back()->with('success', 'Paiement du soutien enregistré.');
    }
}
