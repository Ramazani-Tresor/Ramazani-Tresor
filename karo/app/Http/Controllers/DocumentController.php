<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\Payment;
use App\Models\Student;
use App\Services\BalanceService;
use App\Services\DocumentService;
use App\Services\QrService;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Documents financiers : le contenu est figé à l'émission (instantané), numéroté, horodaté, signé par
 * l'émetteur et vérifiable par QR code. Un document provisoire est marqué comme tel ; un document dont
 * l'opération a été contrepassée passe au statut « annulé » à la vérification.
 */
class DocumentController extends Controller
{
    public function __construct(private DocumentService $docs) {}

    public function issue(Request $request, Student $student, BalanceService $balances)
    {
        $user = $request->user();
        $self = $user->student_id === $student->id && $user->hasPermission('student.self');
        abort_unless($self || $user->canAccessStudent($student, 'documents.issue'), 403);
        $d = $request->validate(['type' => 'required|in:receipt,statement,attestation,situation', 'year' => 'nullable|integer', 'payment_id' => 'nullable|integer']);
        if ($d['type'] === 'receipt') {
            abort_if($self, 403);
            $p = Payment::query()->with(['allocations.charge.category', 'bankAccount.bank', 'academicYear'])->where('student_id', $student->id)->findOrFail($d['payment_id']);
            if (in_array($p->status, ['rejected', 'reversed'], true) || $p->type !== 'bank_deposit') {
                throw new BusinessException('Aucun reçu ne peut être émis pour ce paiement.');
            }
            $final = $p->status === 'validated';
            $doc = $this->docs->issue('receipt', $student, $p,
                ['payment' => $p->number, 'amount' => (string) $p->amount, 'currency' => $p->currency, 'deposit_date' => $p->deposit_date->toDateString()], $user, $final ? 'final' : 'provisional',
                ['payment' => $p->only(['number', 'bank_reference', 'amount', 'currency', 'status', 'bank_status']) + ['deposit_date' => $p->deposit_date->format('d/m/Y'), 'account' => $p->bankAccount->display(), 'year' => $p->academicYear->code],
                    'allocations' => $p->allocations->whereIn('status', $final ? ['active'] : ['proposed'])->map(fn ($a) => ['label' => $a->charge->category->label, 'amount' => (string) $a->amount])->values()->all(),
                    'available' => $final ? $p->availableAmount() : null, 'student' => $this->studentBlock($student, $p->academic_year_id)]);
        } else {
            $year = AcademicYear::query()->findOrFail($d['year'] ?? AcademicYear::current()?->id);
            $sit = $balances->situation($student, $year->id);
            $pending = collect($sit['totals'])->contains(fn ($t) => Money::isPositive($t['pending']));
            $content = ['year' => $year->code, 'student' => $this->studentBlock($student, $year->id), 'totals' => $sit['totals'],
                'lines' => collect($sit['lines'])->map(fn ($l) => ['label' => $l['charge']->category->label, 'currency' => $l['charge']->currency, 'net' => $l['net'], 'settled' => $l['settled'], 'balance' => (string) $l['balance'], 'state' => $l['state']])->all(),
                'payments' => $sit['payments']->where('status', 'validated')->map(fn ($p) => ['number' => $p->number, 'date' => $p->deposit_date?->format('d/m/Y'), 'amount' => (string) $p->amount, 'currency' => $p->currency, 'bank' => $p->bank_status])->values()->all(),
                'pending_note' => $pending ? 'Des paiements déclarés sont en attente de validation et ne sont pas pris en compte.' : null];
            $summary = ['year' => $year->code] + collect($sit['totals'])->map(fn ($t) => ['balance' => $t['balance'], 'settled' => $t['settled']])->all();
            $doc = $this->docs->issue($d['type'], $student, $year, $summary, $user, 'final', $content);
        }

        return redirect()->route('documents.show', $doc);
    }

    public function show(Request $request, IssuedDocument $document, QrService $qr)
    {
        $this->authorizeDoc($request, $document);

        return view('documents.print', $this->data($document, $qr) + ['pdf' => false]);
    }

    public function pdf(Request $request, IssuedDocument $document, QrService $qr)
    {
        $this->authorizeDoc($request, $document);

        return Pdf::loadView('documents.print', $this->data($document, $qr) + ['pdf' => true])->setPaper('a4')->download($document->number.'.pdf');
    }

    /** Vérification publique : données minimales, pas de nom complet ni de détail des frais. */
    public function verify(string $token)
    {
        $doc = IssuedDocument::query()->with('issuer')->where('verification_token', $token)->first();
        if ($doc && $doc->status !== 'cancelled' && $doc->subject instanceof Payment && $doc->subject->status === 'reversed') {
            $doc->status = 'cancelled';
        }

        return response()->view('documents.verify', ['doc' => $doc], $doc ? 200 : 404);
    }

    private function data(IssuedDocument $doc, QrService $qr): array
    {
        $url = $this->docs->verificationUrl($doc);

        return ['doc' => $doc->load('issuer'), 'shop' => Settings::all(), 'qr' => Settings::bool('document_verification_enabled') ? $qr->dataUri($url, 120) : null, 'url' => $url];
    }

    private function studentBlock(Student $student, int $yearId): array
    {
        $e = $student->enrollments()->with('promotion.program.department.faculty')->where('academic_year_id', $yearId)->first();

        return ['matricule' => $student->matricule, 'name' => $student->fullName(), 'promotion' => $e?->promotion->name, 'faculty' => $e?->promotion->program->department->faculty->name];
    }

    private function authorizeDoc(Request $request, IssuedDocument $doc): void
    {
        $u = $request->user();
        abort_unless(($u->student_id === $doc->student_id && $u->hasPermission('student.self')) || $u->canAccessStudent($doc->student, 'students.view') || $u->canAccessStudent($doc->student, 'payments.view'), 403);
    }
}
