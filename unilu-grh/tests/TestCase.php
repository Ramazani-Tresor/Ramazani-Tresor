<?php

namespace Tests;

use App\Models\Employee;
use App\Models\Grade;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\PrimeRate;
use App\Models\PrimeType;
use App\Models\Role;
use App\Models\SituationType;
use App\Models\StaffCategory;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\AssignmentService;
use App\Services\EmployeeService;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected array $u = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // pièces des tests hors du stockage réel
        Settings::flush();
        OrgUnit::flushTree();
        $this->seed(DatabaseSeeder::class);
        Settings::set(['university_name' => 'Université de test (fictive)']);
    }

    protected function world(): array
    {
        $rect = OrgUnit::query()->where('code', 'RECT')->first();
        $fs = OrgUnit::create(['parent_id' => $rect->id, 'type' => 'faculty', 'code' => 'FSCI', 'name' => 'Faculté des Sciences (test)']);
        $dep = OrgUnit::create(['parent_id' => $fs->id, 'type' => 'department', 'code' => 'DCHIM', 'name' => 'Département de Chimie (test)']);
        $fd = OrgUnit::create(['parent_id' => $rect->id, 'type' => 'faculty', 'code' => 'FDROIT', 'name' => 'Faculté de Droit (test)']);
        $srv = OrgUnit::create(['parent_id' => $rect->id, 'type' => 'admin_service', 'code' => 'SPERS', 'name' => 'Service du personnel (test)']);
        OrgUnit::flushTree();
        $this->u = [
            'admin' => User::query()->where('username', 'admin')->first(),
            'hr' => $this->makeUser('rh', 'hr_admin'),
            'hr2' => $this->makeUser('rh2', 'hr_admin'),
            'clerk' => $this->makeUser('gestionnaire', 'personnel_manager'),
            'fin' => $this->makeUser('finance', 'finance_manager'),
            'fin2' => $this->makeUser('finance2', 'finance_manager'),
            'asset' => $this->makeUser('patrimoine', 'asset_manager'),
            'dean' => $this->makeUser('doyen', 'faculty_head', $fs->id),
            'acad' => $this->makeUser('acad', 'academic_officer', null, 'academic'),
        ];

        return compact('rect', 'fs', 'dep', 'fd', 'srv');
    }

    protected function makeUser(string $username, string $role, ?int $unitId = null, ?string $family = null): User
    {
        $u = User::create(['name' => ucfirst($username).' (test)', 'username' => $username, 'email' => $username.'@test.invalid', 'password' => 'Secret!2026grh']);
        UserRoleAssignment::create(['user_id' => $u->id, 'role_id' => Role::query()->where('slug', $role)->value('id'), 'org_unit_id' => $unitId, 'staff_family' => $family]);

        return $u->fresh();
    }

    protected function grade(string $code): Grade
    {
        return Grade::query()->where('code', $code)->firstOrFail();
    }

    protected function employee(OrgUnit $unit, array $attrs = [], ?string $start = '2024-01-01'): Employee
    {
        static $n = 0;
        $n++;
        $grade = $this->grade($attrs['grade'] ?? 'ASS');
        unset($attrs['grade']);
        $e = app(EmployeeService::class)->create($attrs + [
            'matricule' => 'T-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'last_name' => 'AGENT'.$n, 'first_name' => 'Test', 'sex' => 'F',
            'staff_family' => $grade->family, 'staff_category_id' => $grade->staff_category_id, 'grade_id' => $grade->id,
            'engagement_date' => $start, 'first_service_date' => $start, 'email' => "agent{$n}@test.invalid",
        ], $this->u['hr']);
        app(AssignmentService::class)->assign($e, ['org_unit_id' => $unit->id, 'start_date' => $start, 'reason' => 'Initiale'], $this->u['hr'], true, false);

        return $e->fresh();
    }

    protected function monthlyPrime(string $amount = '300.00', array $criteria = [], string $currency = 'USD'): PrimeType
    {
        $type = PrimeType::query()->where('code', 'PRIME_MENS')->first();
        PrimeRate::create($criteria + ['prime_type_id' => $type->id, 'amount' => $amount, 'currency' => $currency]);

        return $type;
    }

    protected function situationType(string $code): SituationType
    {
        return SituationType::query()->where('code', $code)->firstOrFail();
    }

    protected function d(string $date): Carbon
    {
        return Carbon::parse($date);
    }

    protected function category(string $code): StaffCategory
    {
        return StaffCategory::query()->where('code', $code)->firstOrFail();
    }

    protected function position(OrgUnit $unit, string $title, int $seats = 1): Position
    {
        return Position::create(['org_unit_id' => $unit->id, 'code' => 'P-'.$unit->code.'-'.substr(md5($title), 0, 5), 'title' => $title, 'seats' => $seats]);
    }
}
