<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\BankAccount;
use App\Models\Device;
use App\Models\Student;
use App\Models\SyncOperation;
use App\Services\SyncService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function __construct(private SyncService $sync) {}

    public function index(Request $request)
    {
        $u = $request->user();

        return view('sync.index', [
            'ops' => SyncOperation::query()->with(['user', 'result'])->when(! $u->hasPermission('sync.resolve'), fn ($q) => $q->where('user_id', $u->id))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))->latest('id')->paginate(25)->withQueryString(),
            'devices' => $u->hasPermission('sync.resolve') ? Device::query()->with('user')->orderByDesc('last_seen_at')->limit(30)->get() : collect(),
            'years' => AcademicYear::query()->where('status', 'open')->orderByDesc('is_current')->orderByDesc('starts_on')->get(), 'accounts' => BankAccount::query()->with('bank')->where('is_active', true)->get(),
        ]);
    }

    /**
     * Instantané hors connexion LIMITÉ : étudiants du périmètre (matricule, nom, promotion), soldes résumés
     * par devise, comptes bancaires. Pas de coordonnées, pas de justificatifs. Expire après la durée paramétrée.
     */
    public function snapshot(Request $request)
    {
        $u = $request->user();
        $year = AcademicYear::current();
        $scope = $u->facultyScope('payments.create');
        $this->sync->touchDevice((string) $request->header('X-Device-Id', 'inconnu'), $u);
        $students = Student::query()->inFaculties($scope, $year?->id)
            ->with(['enrollments' => fn ($q) => $q->where('academic_year_id', $year?->id)->with('promotion')])->orderBy('last_name')->limit(5000)->get()
            ->map(fn ($s) => ['matricule' => $s->matricule, 'name' => $s->fullName(), 'promotion' => $s->enrollments->first()?->promotion->code]);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'expires_at' => now()->addHours(Settings::int('offline_ttl_hours'))->toIso8601String(),
            'year' => $year ? ['id' => $year->id, 'code' => $year->code] : null,
            'students' => $students,
            'accounts' => BankAccount::query()->with('bank')->where('is_active', true)->get()->map(fn ($a) => ['id' => $a->id, 'label' => $a->display(), 'currency' => $a->currency]),
        ]);
    }

    public function push(Request $request)
    {
        $request->validate(['device_id' => 'required|string|max:64', 'operations' => 'required|array|min:1|max:20']);
        $results = [];
        foreach ($request->input('operations') as $op) {
            $v = Validator::make(is_array($op) ? $op : [], [
                'client_uuid' => 'required|uuid', 'matricule' => 'required|string|max:40', 'academic_year_id' => 'required|integer', 'bank_account_id' => 'required|integer',
                'bank_reference' => 'required|string|max:80', 'deposit_date' => 'required|date', 'amount' => 'required|numeric|gt:0', 'currency' => 'nullable|string|size:3',
                'depositor_name' => 'nullable|string|max:150', 'proof_base64' => 'nullable|string', 'prepared_at' => 'nullable|date',
            ]);
            if ($v->fails()) {
                $results[] = ['client_uuid' => $op['client_uuid'] ?? null, 'status' => 'rejected', 'messages' => $v->errors()->all(), 'payment_number' => null];

                continue;
            }
            $results[] = ['client_uuid' => $op['client_uuid']] + $this->sync->pushPayment($v->validated(), $request->user(), $request->input('device_id'));
        }

        return response()->json(['results' => $results, 'server_time' => now()->toIso8601String()]);
    }

    public function resolve(Request $request, SyncOperation $operation)
    {
        $d = $request->validate(['decision' => 'required|in:accept,reject', 'note' => 'required|string|max:255']);
        $this->sync->resolve($operation, $d['decision'], $request->user(), $d['note']);

        return back()->with('success', $d['decision'] === 'accept' ? 'Saisie acceptée : bordereau créé et soumis au contrôle.' : 'Saisie rejetée.');
    }
}
