<?php

namespace App\Http\Controllers;

use App\Models;
use App\Models\PrimeRate;
use App\Services\AuditService;
use App\Support\Labels;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;

/**
 * Référentiels paramétrables sans intervention informatique. Aucune suppression : une valeur obsolète est désactivée,
 * l'historique qui l'utilise reste intact. Le changement d'année académique ne supprime jamais rien.
 */
class ReferenceController extends Controller
{
    /** ref => [modèle, titre, champs [nom => [libellé, type, options]] ] */
    public const REFS = [
        'annees' => [Models\AcademicYear::class, 'Années académiques', ['code' => ['Code (ex. 2026-2027)', 'text'], 'starts_on' => ['Début', 'date'], 'ends_on' => ['Fin', 'date'], 'status' => ['Statut', 'select', 'year'], 'is_current' => ['Année en cours', 'bool']]],
        'categories' => [Models\StaffCategory::class, 'Catégories du personnel', ['family' => ['Famille', 'select', 'family'], 'code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'sort_order' => ['Ordre', 'number'], 'description' => ['Description', 'text'], 'is_active' => ['Active', 'bool']]],
        'grades' => [Models\Grade::class, 'Grades', ['family' => ['Famille', 'select', 'family'], 'staff_category_id' => ['Catégorie', 'model', Models\StaffCategory::class], 'code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'rank' => ['Rang hiérarchique', 'number'], 'has_mandates' => ['Avec mandats', 'bool'], 'description' => ['Caractéristiques administratives et financières', 'textarea'], 'is_active' => ['Actif', 'bool']]],
        'mandats' => [Models\Mandate::class, 'Mandats', ['grade_id' => ['Grade', 'model', Models\Grade::class], 'code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'sequence' => ['Ordre', 'number'], 'duration_months' => ['Durée (mois)', 'number'], 'is_active' => ['Actif', 'bool']]],
        'fonctions' => [Models\JobFunction::class, 'Fonctions et responsabilités', ['kind' => ['Nature', 'select', 'function_kind'], 'code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'is_responsibility' => ['Responsabilité administrative', 'bool'], 'is_active' => ['Active', 'bool']]],
        'situations' => [Models\SituationType::class, 'Types de congés et situations (règles financières)', ['code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'kind' => ['Nature', 'select', 'situation_kind'], 'resulting_status' => ['Statut de l’agent', 'select', 'employee'], 'prime_percent' => ['% des primes dû', 'number'], 'closes_assignment' => ['Clôture l’affectation', 'bool'], 'ends_employment' => ['Met fin aux droits ordinaires', 'bool'], 'requires_end_date' => ['Date de fin obligatoire', 'bool'], 'max_days' => ['Durée maximale (jours)', 'number'], 'requires_approval' => ['Approbation requise', 'bool'], 'is_active' => ['Actif', 'bool']]],
        'primes' => [Models\PrimeType::class, 'Types de primes et périodicités', ['code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'periodicity' => ['Périodicité', 'select', 'periodicity'], 'prorata' => ['Calcul au prorata des jours', 'bool'], 'description' => ['Règle de calcul (description)', 'textarea'], 'is_active' => ['Actif', 'bool']]],
        'parentes' => [Models\KinshipDegree::class, 'Degrés de parenté (aides au deuil)', ['code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'support_amount' => ['Montant de l’aide', 'decimal'], 'currency' => ['Devise', 'text'], 'conditions' => ['Conditions', 'textarea'], 'is_active' => ['Actif', 'bool']]],
        'soutiens' => [Models\SupportType::class, 'Types de soutiens sociaux', ['code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'uses_kinship' => ['Selon le degré de parenté', 'bool'], 'default_amount' => ['Montant par défaut', 'decimal'], 'max_amount' => ['Plafond', 'decimal'], 'currency' => ['Devise', 'text'], 'is_active' => ['Actif', 'bool']]],
        'documents' => [Models\DocumentType::class, 'Types de pièces numérisées', ['code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'visible_to_agent' => ['Téléchargeable par l’agent', 'bool'], 'is_active' => ['Actif', 'bool']]],
        'patrimoine' => [Models\AssetCategory::class, 'Catégories de biens', ['kind' => ['Nature', 'select', 'asset_kind'], 'code' => ['Code', 'text'], 'name' => ['Libellé', 'text'], 'is_active' => ['Active', 'bool']]],
    ];

    private function def(string $ref): array
    {
        abort_unless(isset(self::REFS[$ref]), 404);

        return self::REFS[$ref];
    }

    public static function options(string $key): array
    {
        return match ($key) {
            'family' => PermissionRegistry::FAMILIES,
            'function_kind' => ['scientific' => 'Scientifique', 'administrative' => 'Administrative', 'technical' => 'Technique', 'other' => 'Autre'],
            default => Labels::options($key),
        };
    }

    private function rules(array $fields, string $model, ?int $id = null): array
    {
        $table = (new $model)->getTable();
        $rules = [];
        foreach ($fields as $name => [$label, $type]) {
            $rules[$name] = match ($type) {
                'bool' => 'nullable|boolean', 'number' => 'nullable|integer|min:0|max:1000000', 'decimal' => 'nullable|numeric|min:0',
                'date' => 'required|date', 'model' => 'nullable|integer', 'select' => 'required|string|max:40', 'textarea' => 'nullable|string|max:2000',
                default => $name === 'code' ? "required|string|max:40|unique:{$table},code".($id ? ",{$id}" : '') : ($name === 'name' ? 'required|string|max:200' : 'nullable|string|max:255'),
            };
        }
        if (isset($fields['prime_percent'])) {
            $rules['prime_percent'] = 'required|integer|min:0|max:100';
        }

        return $rules;
    }

    private function save(Request $request, string $ref, ?int $id = null)
    {
        [$model, $title, $fields] = $this->def($ref);
        $data = $request->validate($this->rules($fields, $model, $id));
        foreach ($fields as $name => [$l, $type]) {
            if ($type === 'bool') {
                $data[$name] = $request->boolean($name);
            }
        }
        $row = $id ? $model::query()->findOrFail($id) : new $model;
        $old = $row->exists ? $row->only(array_keys($data)) : null;
        $row->fill($data)->save();
        if ($ref === 'annees' && $row->is_current) {
            $model::query()->where('id', '!=', $row->id)->update(['is_current' => false]);
        }
        AuditService::log('refs.'.($id ? 'update' : 'create'), $row, "{$title} : ".($row->name ?? $row->code), $old, $row->only(array_keys($data)));

        return back()->with('success', 'Référentiel enregistré.');
    }

    public function index(string $ref)
    {
        [$model, $title, $fields] = $this->def($ref);

        return view('settings.ref', ['ref' => $ref, 'title' => $title, 'fields' => $fields, 'rows' => $model::query()->orderBy(isset($fields['family']) ? 'family' : 'id')->get(), 'refs' => self::REFS]);
    }

    public function store(Request $request, string $ref)
    {
        return $this->save($request, $ref);
    }

    public function update(Request $request, string $ref, int $id)
    {
        return $this->save($request, $ref, $id);
    }

    // Taux de primes (montants librement définis par l'administration)
    public function rates()
    {
        return view('settings.rates', ['rates' => PrimeRate::query()->with(['primeType', 'academicYear', 'category', 'grade', 'jobFunction', 'orgUnit'])->orderBy('prime_type_id')->get(), 'years' => Models\AcademicYear::query()->orderByDesc('starts_on')->get()] + EmployeeController::refs() + ['types' => Models\PrimeType::query()->orderBy('name')->get()]);
    }

    private function rateRules(): array
    {
        return ['prime_type_id' => 'required|exists:prime_types,id', 'academic_year_id' => 'nullable|exists:academic_years,id', 'staff_family' => 'nullable|in:academic,scientific,administrative', 'staff_category_id' => 'nullable|exists:staff_categories,id', 'grade_id' => 'nullable|exists:grades,id', 'job_function_id' => 'nullable|exists:job_functions,id', 'org_unit_id' => 'nullable|exists:org_units,id', 'amount' => 'required|numeric|min:0', 'currency' => 'required|string|size:3', 'valid_from' => 'nullable|date', 'valid_to' => 'nullable|date|after_or_equal:valid_from'];
    }

    public function storeRate(Request $request)
    {
        $r = PrimeRate::create($request->validate($this->rateRules()) + ['is_active' => true]);
        AuditService::log('rates.create', $r, "Taux {$r->amount} {$r->currency} pour {$r->primeType->name}", null, $r->toArray());

        return back()->with('success', 'Taux enregistré. Il s’appliquera aux prochaines générations (les droits déjà générés ne sont pas modifiés).');
    }

    public function updateRate(Request $request, PrimeRate $rate)
    {
        $data = $request->validate(['valid_to' => 'nullable|date', 'is_active' => 'nullable|boolean']);
        $data['is_active'] = $request->boolean('is_active');
        $old = $rate->only(array_keys($data));
        $rate->update($data);
        AuditService::log('rates.update', $rate, 'Modification de la validité du taux', $old, $data);

        return back()->with('success', 'Taux mis à jour.');
    }
}
