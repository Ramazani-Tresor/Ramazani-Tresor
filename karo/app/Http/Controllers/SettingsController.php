<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Cycle;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\ExchangeRate;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Promotion;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private const STRUCTURE = ['faculty' => Faculty::class, 'department' => Department::class, 'cycle' => Cycle::class, 'program' => Program::class, 'promotion' => Promotion::class];

    public function index(BackupService $backups)
    {
        return view('settings.index', [
            's' => Settings::all(), 'sequences' => DocumentSequence::query()->orderBy('id')->get(),
            'faculties' => Faculty::query()->withCount('departments')->orderBy('code')->get(), 'departments' => Department::query()->with('faculty')->orderBy('code')->get(),
            'cycles' => Cycle::all(), 'programs' => Program::query()->with('department', 'cycle')->orderBy('code')->get(), 'promotions' => Promotion::query()->with('program')->orderBy('code')->get(),
            'banks' => Bank::query()->with('accounts')->get(), 'currencies' => Currency::all(), 'rates' => ExchangeRate::query()->with('approver')->latest('effective_on')->limit(20)->get(),
            'backups' => can_do('backups.manage') ? rescue(fn () => $backups->list(), [], false) : [], 'timezones' => timezone_identifiers_list(),
            'backupKeyOk' => (bool) config('karo.backup.key'), 'secondary' => config('karo.backup.secondary_path'),
        ]);
    }

    public function updateInstitution(Request $request)
    {
        $d = $request->validate(['university_name' => 'required|string|max:200', 'university_acronym' => 'nullable|string|max:30', 'university_address' => 'nullable|string|max:255',
            'university_phone' => 'nullable|string|max:80', 'university_email' => 'nullable|email|max:150', 'timezone' => 'required|timezone',
            'signatory_1_name' => 'nullable|string|max:150', 'signatory_1_title' => 'nullable|string|max:150', 'signatory_2_name' => 'nullable|string|max:150', 'signatory_2_title' => 'nullable|string|max:150',
            'logo' => 'nullable|file|max:1024']);
        if ($request->hasFile('logo')) {
            $d['university_logo'] = $this->storePrivateFile($request->file('logo'), 'branding', ['image/png' => 'png', 'image/jpeg' => 'jpg'])['path'];
        }
        unset($d['logo']);
        $this->save($d, 'Identité de l’institution');

        return back()->with('success', 'Informations institutionnelles enregistrées.');
    }

    public function updateRules(Request $request)
    {
        $d = $request->validate(['reconciliation_date_tolerance_days' => 'required|integer|min:0|max:30', 'offline_ttl_hours' => 'required|integer|min:1|max:720',
            'offline_max_payload_kb' => 'required|integer|min:100|max:10240', 'contract_default_conditions' => 'nullable|string|max:3000']);
        foreach (['segregation_of_duties', 'require_proof_for_submission', 'require_check_before_validation', 'adjustment_requires_approval', 'reconciliation_auto_exact', 'statement_reference_required', 'document_verification_enabled', 'offline_enabled', 'student_portal_enabled'] as $flag) {
            $d[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        $this->save($d, 'Règles financières et circuits');

        return back()->with('success', 'Règles enregistrées.');
    }

    public function updateSequences(Request $request)
    {
        $d = $request->validate(['seq' => 'required|array', 'seq.*.prefix' => 'required|alpha_dash|max:20', 'seq.*.padding' => 'required|integer|min:3|max:10']);
        foreach ($d['seq'] as $id => $row) {
            DocumentSequence::query()->whereKey($id)->update(['prefix' => strtoupper($row['prefix']), 'padding' => $row['padding']]);
        }
        AuditService::log('settings.sequences', null, 'Numérotation des documents modifiée', null, $d['seq']);

        return back()->with('success', 'Numérotation mise à jour (les numéros déjà attribués ne changent pas).');
    }

    public function storeStructure(Request $request, string $type)
    {
        abort_unless(isset(self::STRUCTURE[$type]), 404);
        $rules = [
            'faculty' => ['code' => 'required|alpha_dash|max:20|unique:faculties,code', 'name' => 'required|string|max:150'],
            'department' => ['faculty_id' => 'required|exists:faculties,id', 'code' => 'required|alpha_dash|max:20', 'name' => 'required|string|max:150'],
            'cycle' => ['code' => 'required|alpha_dash|max:20|unique:cycles,code', 'name' => 'required|string|max:100', 'duration_years' => 'required|integer|min:1|max:10'],
            'program' => ['department_id' => 'required|exists:departments,id', 'cycle_id' => 'required|exists:cycles,id', 'code' => 'required|alpha_dash|max:30|unique:programs,code', 'name' => 'required|string|max:150'],
            'promotion' => ['program_id' => 'required|exists:programs,id', 'code' => 'required|alpha_dash|max:40|unique:promotions,code', 'level' => 'required|string|max:20', 'name' => 'required|string|max:150', 'sort_order' => 'nullable|integer'],
        ][$type];
        $d = $request->validate($rules);
        if ($type === 'promotion') {
            $d['is_final'] = $request->boolean('is_final');
        }
        $m = self::STRUCTURE[$type]::create($d);
        AuditService::log('structure.create', $m, "Structure académique : {$type} {$m->code} créé(e)");

        return back()->with('success', 'Élément ajouté.');
    }

    public function toggleStructure(string $type, int $id)
    {
        abort_unless(isset(self::STRUCTURE[$type]) && $type !== 'cycle', 404);
        $m = self::STRUCTURE[$type]::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);
        AuditService::log('structure.toggle', $m, ($m->is_active ? 'Réactivation' : 'Désactivation')." de {$type} {$m->code}");

        return back()->with('success', $m->is_active ? 'Réactivé.' : 'Désactivé (historique conservé).');
    }

    public function storeBank(Request $request)
    {
        $d = $request->validate(['code' => 'required|alpha_dash|max:20|unique:banks,code', 'name' => 'required|string|max:150']);
        $b = Bank::create($d);
        AuditService::log('bank.create', $b, "Banque {$b->name} créée");

        return back()->with('success', 'Banque ajoutée.');
    }

    public function storeAccount(Request $request)
    {
        $d = $request->validate(['bank_id' => 'required|exists:banks,id', 'account_number' => 'required|string|max:60|unique:bank_accounts,account_number', 'label' => 'required|string|max:150', 'currency' => 'required|exists:currencies,code']);
        $a = BankAccount::create($d);
        AuditService::log('bank_account.create', $a, "Compte bénéficiaire {$a->account_number} ({$a->currency}) créé");

        return back()->with('success', 'Compte ajouté. Autorisez-le ensuite pour les frais concernés.');
    }

    public function toggleAccount(BankAccount $account)
    {
        $account->update(['is_active' => ! $account->is_active]);
        AuditService::log('bank_account.toggle', $account, ($account->is_active ? 'Activation' : 'Désactivation')." du compte {$account->account_number}");

        return back()->with('success', 'Compte mis à jour.');
    }

    public function storeCurrency(Request $request)
    {
        $d = $request->validate(['code' => 'required|alpha|size:3|unique:currencies,code', 'name' => 'required|string|max:60', 'symbol' => 'required|string|max:6']);
        Currency::create(['code' => strtoupper($d['code'])] + $d);

        return back()->with('success', 'Devise ajoutée.');
    }

    public function storeRate(Request $request)
    {
        $d = $request->validate(['from_currency' => 'required|exists:currencies,code', 'to_currency' => ['required', 'exists:currencies,code', 'different:from_currency'],
            'rate' => 'required|numeric|gt:0', 'effective_on' => ['required', 'date', Rule::unique('exchange_rates')->where(fn ($q) => $q->where('from_currency', $request->input('from_currency'))->where('to_currency', $request->input('to_currency')))], 'source' => 'required|string|max:150']);
        $r = ExchangeRate::create($d + ['approved_by' => $request->user()->id]);
        AuditService::log('rate.approve', $r, "Taux approuvé : 1 {$r->from_currency} = {$r->rate} {$r->to_currency} au {$r->effective_on->format('d/m/Y')}", null, $d);

        return back()->with('success', 'Taux de conversion approuvé et enregistré.');
    }

    public function backup(BackupService $backups)
    {
        $b = $backups->create();

        return back()->with('success', "Sauvegarde chiffrée créée : {$b['name']} (".count($b['copies']).' copie(s)). Empreinte : '.substr($b['sha256'], 0, 16).'…');
    }

    public function downloadBackup(string $file, BackupService $backups)
    {
        AuditService::log('backup.download', null, "Téléchargement de la sauvegarde {$file}");

        return response()->download($backups->path($file));
    }

    private function save(array $d, string $label): void
    {
        $old = array_intersect_key(Settings::all(), $d);
        Settings::set(array_map(fn ($v) => $v ?? '', $d));
        AuditService::log('settings.update', null, $label, $old, $d);
    }
}
