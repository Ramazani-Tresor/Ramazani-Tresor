<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Inventory;
use App\Models\InventoryLine;
use App\Models\User;
use App\Support\Authz;
use Illuminate\Support\Facades\DB;

/** Patrimoine : chaque mouvement (entrée, affectation, transfert, état, réforme, rebut, inventaire) est historisé. */
class AssetService
{
    public function __construct(private NumberingService $numbers) {}

    public function create(array $data, User $user): Asset
    {
        Authz::need($user, 'assets.manage');
        $code = trim((string) ($data['code'] ?? '')) ?: $this->numbers->next('asset');
        if (Asset::query()->where('code', $code)->exists()) {
            throw new BusinessException("Le code patrimonial {$code} existe déjà.");
        }

        return DB::transaction(function () use ($data, $user, $code) {
            $a = Asset::create(array_intersect_key($data, array_flip([
                'asset_category_id', 'designation', 'parent_id', 'location', 'brand_model', 'serial_number', 'quantity', 'acquisition_date',
                'acquisition_mode', 'value', 'currency', 'condition', 'org_unit_id', 'responsible_employee_id', 'notes',
            ])) + ['code' => $code, 'status' => 'in_service', 'created_by' => $user->id]);
            $this->movement($a, 'entry', ['to_unit_id' => $a->org_unit_id, 'new_condition' => $a->condition, 'responsible_employee_id' => $a->responsible_employee_id, 'movement_date' => $data['acquisition_date'] ?? now()->toDateString(), 'reference' => $data['reference'] ?? null, 'notes' => 'Entrée en patrimoine'], $user);
            AuditService::log('assets.create', $a, "Entrée en patrimoine {$a->code} — {$a->designation}");

            return $a;
        });
    }

    public function update(Asset $a, array $data, User $user): Asset
    {
        Authz::need($user, 'assets.manage');
        $a->update(array_intersect_key($data, array_flip(['designation', 'location', 'brand_model', 'serial_number', 'value', 'currency', 'notes', 'parent_id', 'acquisition_mode', 'acquisition_date', 'quantity'])));
        AuditService::log('assets.update', $a, "Mise à jour {$a->code}");

        return $a;
    }

    /** Affectation ou transfert vers une unité (et/ou un responsable). */
    public function transfer(Asset $a, array $data, User $user): AssetMovement
    {
        Authz::need($user, 'assets.manage');
        $this->assertInService($a);
        if (empty($data['to_unit_id']) && empty($data['responsible_employee_id'])) {
            throw new BusinessException('Indiquez la structure ou le responsable de destination.');
        }

        return DB::transaction(function () use ($a, $data, $user) {
            $type = $a->org_unit_id ? 'transfer' : 'assignment';
            $mv = $this->movement($a, $type, ['from_unit_id' => $a->org_unit_id, 'to_unit_id' => $data['to_unit_id'] ?? $a->org_unit_id, 'responsible_employee_id' => $data['responsible_employee_id'] ?? $a->responsible_employee_id, 'movement_date' => $data['movement_date'] ?? now()->toDateString(), 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null, 'location' => $data['location'] ?? null], $user);
            $a->update(['org_unit_id' => $data['to_unit_id'] ?? $a->org_unit_id, 'responsible_employee_id' => $data['responsible_employee_id'] ?? $a->responsible_employee_id, 'location' => $data['location'] ?? $a->location]);

            return $mv;
        });
    }

    public function changeCondition(Asset $a, string $condition, User $user, ?string $notes = null, ?string $date = null): AssetMovement
    {
        Authz::need($user, 'assets.manage');
        $this->assertInService($a);
        $mv = $this->movement($a, 'condition', ['old_condition' => $a->condition, 'new_condition' => $condition, 'movement_date' => $date ?? now()->toDateString(), 'notes' => $notes], $user);
        $a->update(['condition' => $condition]);

        return $mv;
    }

    /** Réforme (retrait du service) puis, éventuellement, mise au rebut. Le bien reste dans l'historique. */
    public function retire(Asset $a, string $type, string $reference, User $user, ?string $notes = null): AssetMovement
    {
        Authz::need($user, 'assets.manage');
        if (! in_array($type, ['reform', 'scrap'], true)) {
            throw new BusinessException('Opération inconnue.');
        }
        if ($a->status === 'scrapped') {
            throw new BusinessException('Ce bien est déjà mis au rebut.');
        }
        if (trim($reference) === '') {
            throw new BusinessException('La référence de la décision (procès-verbal de réforme ou de mise au rebut) est obligatoire.');
        }
        $mv = $this->movement($a, $type, ['from_unit_id' => $a->org_unit_id, 'old_condition' => $a->condition, 'movement_date' => now()->toDateString(), 'reference' => $reference, 'notes' => $notes], $user);
        $a->update(['status' => $type === 'reform' ? 'reformed' : 'scrapped']);

        return $mv;
    }

    public function openInventory(array $data, User $user): Inventory
    {
        Authz::need($user, 'inventories.manage');
        $inv = Inventory::create(['reference' => $this->numbers->next('inventory'), 'label' => $data['label'], 'org_unit_id' => $data['org_unit_id'] ?? null, 'started_on' => $data['started_on'] ?? now()->toDateString(), 'status' => 'open', 'created_by' => $user->id]);
        AuditService::log('inventories.open', $inv, "Ouverture de l’inventaire {$inv->reference}");

        return $inv;
    }

    public function check(Inventory $inv, Asset $a, bool $found, ?string $condition, ?string $location, ?string $notes, User $user): InventoryLine
    {
        Authz::need($user, 'inventories.manage');
        if ($inv->status !== 'open') {
            throw new BusinessException('Inventaire clôturé.');
        }

        return InventoryLine::updateOrCreate(['inventory_id' => $inv->id, 'asset_id' => $a->id], ['found' => $found, 'observed_condition' => $condition, 'observed_location' => $location, 'notes' => $notes, 'checked_by' => $user->id]);
    }

    /** Clôture : l'état constaté met à jour les biens et chaque constat devient un mouvement « inventaire ». */
    public function closeInventory(Inventory $inv, User $user): array
    {
        Authz::need($user, 'inventories.manage');
        if ($inv->status !== 'open') {
            throw new BusinessException('Inventaire déjà clôturé.');
        }

        return DB::transaction(function () use ($inv, $user) {
            $stats = ['checked' => 0, 'missing' => 0, 'condition_changed' => 0];
            foreach ($inv->lines()->with('asset')->get() as $line) {
                $a = $line->asset;
                $stats['checked']++;
                $stats['missing'] += $line->found ? 0 : 1;
                $newCond = $line->found ? ($line->observed_condition ?: $a->condition) : $a->condition;
                $this->movement($a, 'inventory', ['old_condition' => $a->condition, 'new_condition' => $newCond, 'movement_date' => now()->toDateString(), 'reference' => $inv->reference, 'notes' => $line->found ? ($line->notes ?: 'Constaté présent') : 'NON RETROUVÉ lors de l’inventaire', 'inventory_id' => $inv->id], $user);
                if ($line->found && $newCond !== $a->condition) {
                    $a->update(['condition' => $newCond]);
                    $stats['condition_changed']++;
                }
                if ($line->found && $line->observed_location) {
                    $a->update(['location' => $line->observed_location]);
                }
            }
            $inv->update(['status' => 'closed', 'closed_on' => now()->toDateString()]);
            AuditService::log('inventories.close', $inv, "Clôture de l’inventaire {$inv->reference}", null, $stats);

            return $stats;
        });
    }

    private function assertInService(Asset $a): void
    {
        if ($a->status !== 'in_service') {
            throw new BusinessException('Ce bien est réformé ou mis au rebut : aucune affectation ni modification d’état n’est possible.');
        }
    }

    private function movement(Asset $a, string $type, array $data, User $user): AssetMovement
    {
        unset($data['location']);

        return AssetMovement::create(['asset_id' => $a->id, 'type' => $type, 'user_id' => $user->id] + $data);
    }
}
