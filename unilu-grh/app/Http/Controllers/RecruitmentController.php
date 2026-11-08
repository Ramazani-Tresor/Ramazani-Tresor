<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Services\AuditService;
use App\Services\DocumentStore;
use App\Services\RecruitmentService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecruitmentController extends Controller
{
    public function index(Request $request)
    {
        $q = Candidate::query()->with(['grade', 'orgUnit'])->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }

        return view('recruitment.index', ['candidates' => $q->paginate(30)->withQueryString()] + EmployeeController::refs());
    }

    public function store(Request $request, RecruitmentService $svc)
    {
        $data = $request->validate([
            'last_name' => 'required|string|max:120', 'middle_name' => 'nullable|string|max:120', 'first_name' => 'nullable|string|max:120', 'sex' => 'required|in:M,F',
            'birth_date' => 'nullable|date|before:today', 'birth_place' => 'nullable|string|max:120', 'nationality' => 'nullable|string|max:80',
            'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:150', 'address' => 'nullable|string|max:255',
            'staff_family' => 'required|in:academic,scientific,administrative', 'staff_category_id' => 'nullable|exists:staff_categories,id', 'grade_id' => 'nullable|exists:grades,id',
            'org_unit_id' => 'nullable|exists:org_units,id', 'position_id' => 'nullable|exists:positions,id', 'job_function_id' => 'nullable|exists:job_functions,id',
            'highest_degree' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:2000',
        ]);
        $c = $svc->create($data, $request->user());

        return redirect()->route('recruitment.show', $c)->with('success', "Candidature {$c->reference} enregistrée.");
    }

    public function show(Candidate $candidate)
    {
        return view('recruitment.show', ['c' => $candidate->load(['grade', 'category', 'orgUnit', 'position', 'jobFunction', 'documents', 'employee'])] + EmployeeController::refs());
    }

    public function status(Request $request, Candidate $candidate, RecruitmentService $svc)
    {
        $data = $request->validate(['status' => 'required|in:under_review,validated,rejected', 'reason' => 'nullable|required_if:status,rejected|string|max:500']);
        $svc->transition($candidate, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Statut de la candidature mis à jour.');
    }

    public function upload(Request $request, Candidate $candidate, DocumentStore $store)
    {
        $request->validate(['document' => 'required|file|max:'.(Settings::int('max_upload_kb') ?: 10240), 'title' => 'required|string|max:200']);
        $raw = $store->storeRaw($request->file('document'), 'recrutement/'.$candidate->id);
        $doc = CandidateDocument::create($raw + ['candidate_id' => $candidate->id, 'title' => $request->input('title'), 'uploaded_by' => $request->user()->id]);
        AuditService::log('recruitment.upload', $doc, "Pièce « {$doc->title} » ajoutée à {$candidate->reference}");

        return back()->with('success', 'Pièce numérisée ajoutée.');
    }

    public function document(CandidateDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return response()->file(Storage::disk('local')->path($document->path), ['Cache-Control' => 'private, no-store']);
    }

    public function engage(Request $request, Candidate $candidate, RecruitmentService $svc)
    {
        $data = $request->validate([
            'matricule' => 'nullable|string|max:40|unique:employees,matricule', 'decision_number' => 'required|string|max:80', 'decision_date' => 'required|date',
            'signing_authority' => 'required|string|max:150', 'engagement_date' => 'required|date', 'org_unit_id' => 'required|exists:org_units,id',
            'position_id' => 'nullable|exists:positions,id', 'staff_family' => 'nullable|in:academic,scientific,administrative',
            'staff_category_id' => 'nullable|exists:staff_categories,id', 'grade_id' => 'nullable|exists:grades,id', 'job_function_id' => 'nullable|exists:job_functions,id',
        ]);
        $e = $svc->engage($candidate, $data, $request->user());

        return redirect()->route('employees.show', $e)->with('success', "Engagement prononcé : matricule {$e->matricule} attribué, agent intégré au fichier général.");
    }
}
