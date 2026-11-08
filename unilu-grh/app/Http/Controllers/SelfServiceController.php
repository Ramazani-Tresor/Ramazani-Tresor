<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\CorrectionService;
use App\Services\DocumentStore;
use App\Services\PrimeService;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Espace personnel : l'agent ne voit que son propre dossier et ne peut modifier aucune décision (demande de correction). */
class SelfServiceController extends Controller
{
    private function me(Request $request): Employee
    {
        abort_unless(Settings::bool('self_service_enabled'), 403);
        $e = $request->user()->employee;
        abort_unless($e, 403, 'Aucun dossier d’agent n’est rattaché à votre compte.');

        return $e;
    }

    public function show(Request $request)
    {
        $e = $this->me($request)->load(['grade', 'category', 'mandate', 'adminGrade', 'jobFunction', 'activeAssignment.orgUnit', 'activeAssignment.position']);

        return view('me.show', [
            'e' => $e,
            'documents' => $e->documents()->with('type')->whereHas('type', fn ($t) => $t->where('visible_to_agent', true))->orderByDesc('document_date')->get(),
            'qualifications' => $e->qualifications()->with('academicYear')->orderByDesc('defense_date')->get(),
            'assignments' => $e->assignments()->with(['orgUnit', 'position'])->orderByDesc('start_date')->get(),
        ]);
    }

    public function career(Request $request)
    {
        $e = $this->me($request);

        return view('me.career', ['e' => $e->load(['grade', 'mandate']), 'career' => $e->careerEvents()->with(['oldGrade', 'newGrade', 'newMandate', 'newCategory'])->where('status', 'validated')->orderByDesc('effective_date')->get(), 'assignments' => $e->assignments()->with('orgUnit')->orderByDesc('start_date')->get()]);
    }

    public function finances(Request $request, PrimeService $primes)
    {
        $e = $this->me($request);

        return view('me.finances', [
            'e' => $e, 'summary' => $primes->summary($e),
            'entitlements' => $e->entitlements()->with(['primeType', 'adjustments', 'payments'])->orderByDesc('period_start')->get(),
            'payments' => $e->primePayments()->with('entitlement.primeType')->where('status', '!=', 'rejected')->orderByDesc('payment_date')->get(),
            'supports' => $e->socialSupports()->with(['type', 'kinship'])->latest()->get(),
        ]);
    }

    public function situations(Request $request)
    {
        $e = $this->me($request);

        return view('me.situations', ['e' => $e, 'situations' => $e->situations()->with('type')->orderByDesc('start_date')->get()]);
    }

    public function corrections(Request $request)
    {
        $e = $this->me($request);

        return view('me.corrections', ['e' => $e, 'requests' => $e->correctionRequests()->latest()->get(), 'fields' => CorrectionService::FIELDS]);
    }

    public function submitCorrection(Request $request, CorrectionService $svc, DocumentStore $store)
    {
        $e = $this->me($request);
        $data = $request->validate(['field' => 'required|string', 'requested_value' => 'required|string|max:1000', 'justification' => 'required|string|max:1000', 'document' => 'nullable|file|max:5120']);
        if ($request->hasFile('document')) {
            $data['document_id'] = $store->attach($e, $request->file('document'), ['title' => 'Justificatif — demande de correction', 'document_date' => now()->toDateString()])->id;
        }
        $svc->submit($e, $data, $request->user());

        return back()->with('success', 'Demande transmise au service du personnel ; vous serez notifié(e) de la suite.');
    }

    public function sheet(Request $request, PrimeService $primes)
    {
        return EmployeeController::renderSheet($this->me($request), true, $primes);
    }
}
