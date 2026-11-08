<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Employee;
use App\Models\OrgUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->with(['roleAssignments.role', 'roleAssignments.orgUnit', 'employee'])->orderBy('name');
        if ($request->filled('q')) {
            $t = '%'.$request->input('q').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('username', 'like', $t)->orWhere('email', 'like', $t));
        }

        return view('users.index', ['users' => $q->paginate(30)->withQueryString(), 'roles' => Role::query()->orderBy('name')->get()]);
    }

    private function employeeId(?string $matricule, ?int $ignoreUser = null): ?int
    {
        if (! $matricule) {
            return null;
        }
        $e = Employee::query()->where('matricule', $matricule)->first();
        if (! $e) {
            throw new BusinessException("Matricule {$matricule} introuvable.");
        }
        if (User::query()->where('employee_id', $e->id)->when($ignoreUser, fn ($q) => $q->where('id', '!=', $ignoreUser))->exists()) {
            throw new BusinessException('Ce dossier est déjà rattaché à un autre compte.');
        }

        return $e->id;
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'username' => 'required|string|max:60|alpha_dash|unique:users,username', 'email' => 'nullable|email|max:150|unique:users,email', 'matricule' => 'nullable|string|max:40', 'role_id' => 'nullable|exists:roles,id']);
        $temp = Str::password(14);
        $user = User::create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?? null, 'password' => $temp, 'must_change_password' => true, 'employee_id' => $this->employeeId($data['matricule'] ?? null)]);
        if (! empty($data['role_id'])) {
            UserRoleAssignment::create(['user_id' => $user->id, 'role_id' => $data['role_id']]);
        }
        AuditService::log('users.create', $user, "Création du compte {$user->username}");

        return redirect()->route('users.edit', $user)->with('success', "Compte créé. Mot de passe temporaire à transmettre de façon sûre (affiché une seule fois) : {$temp}");
    }

    public function edit(User $user)
    {
        return view('users.edit', ['user' => $user->load(['roleAssignments.role', 'roleAssignments.orgUnit', 'employee']), 'roles' => Role::query()->orderBy('name')->get(), 'units' => OrgUnit::query()->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)], 'matricule' => 'nullable|string|max:40', 'is_active' => 'nullable|boolean']);
        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            throw new BusinessException('Vous ne pouvez pas désactiver votre propre compte.');
        }
        $old = $user->only(['name', 'email', 'is_active', 'employee_id']);
        $user->update(['name' => $data['name'], 'email' => $data['email'] ?? null, 'is_active' => $request->boolean('is_active'), 'employee_id' => $this->employeeId($data['matricule'] ?? null, $user->id)]);
        AuditService::log('users.update', $user, "Modification du compte {$user->username}", $old, $user->only(array_keys($old)));

        return back()->with('success', 'Compte mis à jour.');
    }

    public function assign(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw new BusinessException('Vous ne pouvez pas modifier vos propres habilitations.');
        }
        $data = $request->validate(['role_id' => 'required|exists:roles,id', 'org_unit_id' => 'nullable|exists:org_units,id', 'staff_family' => 'nullable|in:academic,scientific,administrative']);
        $role = Role::query()->findOrFail($data['role_id']);
        if ($role->slug === 'super_admin' && ! $request->user()->hasGlobalPermission('roles.manage')) {
            throw new BusinessException('Seul un super administrateur peut attribuer ce rôle.');
        }
        $a = UserRoleAssignment::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id, 'org_unit_id' => $data['org_unit_id'] ?? null, 'staff_family' => $data['staff_family'] ?? null]);
        AuditService::log('users.assign', $user, "Attribution du rôle {$role->name}", null, $a->only(['role_id', 'org_unit_id', 'staff_family']));

        return back()->with('success', 'Rôle attribué.');
    }

    public function unassign(Request $request, User $user, UserRoleAssignment $assignment)
    {
        abort_unless($assignment->user_id === $user->id, 404);
        if ($user->id === $request->user()->id) {
            throw new BusinessException('Vous ne pouvez pas modifier vos propres habilitations.');
        }
        AuditService::log('users.unassign', $user, 'Retrait du rôle '.$assignment->role?->name, $assignment->only(['role_id', 'org_unit_id', 'staff_family']));
        $assignment->delete();

        return back()->with('success', 'Rôle retiré.');
    }

    public function resetPassword(User $user)
    {
        $temp = Str::password(14);
        $user->forceFill(['password' => $temp, 'must_change_password' => true])->save();
        AuditService::log('users.password', $user, "Réinitialisation du mot de passe de {$user->username}");

        return back()->with('success', "Mot de passe temporaire (à transmettre de façon sûre, affiché une seule fois) : {$temp}");
    }

    public function roles()
    {
        return view('users.roles', ['roles' => Role::query()->with('permissions')->orderBy('name')->get(), 'permissions' => Permission::query()->orderBy('module')->orderBy('slug')->get()->groupBy('module')]);
    }

    public function updateRole(Request $request, Role $role)
    {
        if ($role->slug === 'super_admin') {
            throw new BusinessException('Le rôle super administrateur conserve toutes les permissions.');
        }
        $ids = Permission::query()->whereIn('slug', (array) $request->input('permissions', []))->pluck('id');
        $old = $role->permissions()->pluck('slug')->all();
        $role->permissions()->sync($ids);
        AuditService::log('roles.update', $role, "Permissions du rôle {$role->name}", ['permissions' => $old], ['permissions' => $role->permissions()->pluck('slug')->all()]);

        return back()->with('success', "Permissions du rôle « {$role->name} » enregistrées.");
    }
}
