<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\IssuedDocument;
use App\Models\PaymentContract;
use App\Models\Student;
use App\Services\ContractService;
use App\Services\DocumentService;
use App\Services\QrService;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function __construct(private ContractService $svc) {}

    public function index(Request $request)
    {
        $scope = $request->user()->facultyScope('contracts.view');
        $rows = PaymentContract::query()->with(['student', 'academicYear', 'installments'])
            ->whereHas('student', fn ($s) => $s->inFaculties($scope))
            ->when(! $request->boolean('all'), fn ($q) => $q->whereNotIn('status', ['superseded', 'cancelled']))
            ->latest('id')->limit(500)->get()->map(fn ($c) => ['c' => $c, 'cp' => $this->svc->compliance($c)])
            ->when($request->boolean('breached'), fn ($col) => $col->filter(fn ($r) => $r['cp']['breached']))->values();

        return view('contracts.index', ['rows' => $rows]);
    }

    public function create(Request $request, Student $student)
    {
        $this->authorizeStudent($student, 'contracts.manage');
        $yearId = $request->integer('year');
        $charges = Charge::query()->with('category')->where('student_id', $student->id)->where('academic_year_id', $yearId)->where('status', 'active')->get()->filter(fn ($c) => Money::isPositive($c->balance()));

        return view('contracts.form', ['student' => $student, 'yearId' => $yearId, 'charges' => $charges, 'contract' => null]);
    }

    public function store(Request $request, Student $student)
    {
        $this->authorizeStudent($student, 'contracts.manage');
        $d = $this->validated($request);
        $c = $this->svc->create($student, (int) $request->integer('academic_year_id'), $d['charge_ids'], array_values($d['inst']), $d['conditions'] ?? null, $request->user());

        return redirect()->route('contracts.show', $c)->with('success', 'Contrat préparé : il doit être approuvé par une autre personne habilitée.');
    }

    public function show(PaymentContract $contract)
    {
        $this->authorizeStudent($contract->student, 'contracts.view');

        return view('contracts.show', ['c' => $contract->load(['student', 'academicYear', 'installments', 'creator', 'approver', 'parent']), 'cp' => $this->svc->compliance($contract),
            'versions' => PaymentContract::query()->where('number', $contract->number)->orderByDesc('version')->get(), 'charges' => Charge::query()->with('category')->whereIn('id', $contract->charge_ids)->get()]);
    }

    public function editVersion(PaymentContract $contract)
    {
        $this->authorizeStudent($contract->student, 'contracts.manage');

        return view('contracts.form', ['student' => $contract->student, 'yearId' => $contract->academic_year_id, 'charges' => Charge::query()->with('category')->whereIn('id', $contract->charge_ids)->get(), 'contract' => $contract->load('installments')]);
    }

    public function storeVersion(Request $request, PaymentContract $contract)
    {
        $this->authorizeStudent($contract->student, 'contracts.manage');
        $d = $this->validated($request, true);
        $new = $this->svc->newVersion($contract, array_values($d['inst']), $d['conditions'] ?? null, $d['reason'], $request->user());

        return redirect()->route('contracts.show', $new)->with('success', "Version v{$new->version} créée ; la précédente est conservée comme remplacée.");
    }

    public function approve(Request $request, PaymentContract $contract)
    {
        $this->authorizeStudent($contract->student, 'contracts.approve');
        $this->svc->approve($contract, $request->user());

        return back()->with('success', 'Contrat approuvé : imprimez-le pour signature puis archivez la version signée.');
    }

    public function archiveSigned(Request $request, PaymentContract $contract)
    {
        $this->authorizeStudent($contract->student, 'contracts.approve');
        $request->validate(['file' => 'required|file|max:10240']);
        $f = $this->storePrivateFile($request->file('file'), 'contracts');
        $this->svc->archiveSigned($contract, $f['path'], $request->user());

        return back()->with('success', 'Version signée archivée.');
    }

    public function signed(PaymentContract $contract)
    {
        $this->authorizeStudent($contract->student, 'contracts.view');
        abort_unless($contract->signed_file_path && Storage::exists($contract->signed_file_path), 404);

        return Storage::download($contract->signed_file_path, "{$contract->number}-v{$contract->version}-signe.".pathinfo($contract->signed_file_path, PATHINFO_EXTENSION));
    }

    public function pdf(Request $request, PaymentContract $contract, DocumentService $docs, QrService $qr)
    {
        $this->authorizeStudent($contract->student, 'contracts.view');
        $doc = IssuedDocument::query()->where('subject_type', $contract->getMorphClass())->where('subject_id', $contract->id)->where('status', $contract->status === 'draft' ? 'provisional' : 'final')->first()
            ?? $docs->issue('contract', $contract->student, $contract, ['contract' => $contract->number.' v'.$contract->version, 'amount' => (string) $contract->balance, 'currency' => $contract->currency], $request->user(), $contract->status === 'draft' ? 'provisional' : 'final');
        $data = ['c' => $contract->load(['student', 'academicYear', 'installments']), 'charges' => Charge::query()->with('category')->whereIn('id', $contract->charge_ids)->get(), 'shop' => Settings::all(),
            'doc' => $doc, 'qr' => $qr->dataUri($docs->verificationUrl($doc), 110), 'pdf' => $request->boolean('download')];

        return $request->boolean('download') ? Pdf::loadView('contracts.print', $data)->download("{$contract->number}-v{$contract->version}.pdf") : view('contracts.print', $data);
    }

    private function validated(Request $request, bool $version = false): array
    {
        return $request->validate([
            'charge_ids' => [$version ? 'nullable' : 'required', 'array'], 'charge_ids.*' => 'integer', 'conditions' => 'nullable|string|max:3000',
            'inst' => 'required|array|min:1|max:24', 'inst.*.amount' => 'required|numeric|gt:0', 'inst.*.due_date' => 'required|date',
            'reason' => [$version ? 'required' : 'nullable', 'string', 'max:255'],
        ]);
    }
}
