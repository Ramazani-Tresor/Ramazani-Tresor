<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\Student;
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
        $users = User::query()->with('assignments.role', 'assignments.faculty', 'assignments.academicYear')
            ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('username', 'like', "%{$t}%")))
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))->orderBy('name')->paginate(25)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        $temp = Str::password(14);
        $u = User::create($d + ['password' => $temp, 'must_change_password' => true]);
        AuditService::log('user.create', $u, "Création du compte {$u->username}");

        return redirect()->route('users.edit', $u)->with('warning', "Compte créé. Mot de passe temporaire à transmettre en main propre : {$temp} (affiché une seule fois). Ajoutez maintenant ses rôles et périmètres.");
    }

    public function edit(User $user)
    {
        return view('users.edit', ['user' => $user->load('assignments.role', 'assignments.faculty', 'assignments.academicYear'), 'roles' => Role::query()->orderBy('id')->get(),
            'faculties' => Faculty::query()->orderBy('name')->get(), 'years' => AcademicYear::query()->orderByDesc('starts_on')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $d = $this->validated($request, $user);
        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            throw new BusinessException('Vous ne pouvez pas désactiver votre propre compte.');
        }
        $old = $user->only(['name', 'username', 'email', 'is_active', 'student_id']);
        $user->update($d);
        if ($request->boolean('archive') && $user->id !== $request->user()->id) {
            $user->update(['archived_at' => now(), 'is_active' => false]);
        }
        AuditService::log('user.update', $user, "Modification du compte {$user->username}", $old, $user->only(array_keys($old)));

        return back()->with('success', 'Compte mis à jour.');
    }

    public function addAssignment(Request $request, User $user)
    {
        $d = $request->validate(['role_id' => 'required|exists:roles,id', 'faculty_id' => 'nullable|exists:faculties,id', 'academic_year_id' => 'nullable|exists:academic_years,id']);
        if ($user->id === $request->user()->id) {
            throw new BusinessException('Vous ne pouvez pas modifier vos propres habilitations : demandez à un autre administrateur.');
        }
        $role = Role::query()->with('permissions')->findOrFail($d['role_id']);
        // Garde-fou : la gestion des utilisateurs ne permet pas d'attribuer des approbations financières à un
        // compte qui détient lui-même l'administration technique (cumul interdit).
        $sensitive = $role->permissions->where('is_sensitive', true)->isNotEmpty();
        if ($sensitive && $user->hasPermission('users.manage')) {
            throw new BusinessException('Cumul interdit : un compte d’administration technique ne peut pas recevoir de rôle comportant des approbations financières.');
        }
        if ($role->permissions->contains('slug', 'users.manage') && $user->assignments()->whereHas('role.permissions', fn ($p) => $p->where('is_sensitive', true))->exists()) {
            throw new BusinessException('Cumul interdit : ce compte détient des approbations financières.');
        }
        $a = UserRoleAssignment::create($d + ['user_id' => $user->id]);
        AuditService::log('user.assignment_add', $user, "Rôle {$role->name} attribué à {$user->username}".($a->faculty_id ? ' (faculté '.$a->faculty->code.')' : '').($a->academic_year_id ? ' (année '.$a->academicYear->code.')' : ''));

        return back()->with('success', 'Habilitation ajoutée.');
    }

    public function removeAssignment(Request $request, UserRoleAssignment $assignment)
    {
        if ($assignment->user_id === $request->user()->id) {
            throw new BusinessException('Vous ne pouvez pas modifier vos propres habilitations.');
        }
        $u = $assignment->user;
        $label = $assignment->role->name;
        $assignment->delete();
        AuditService::log('user.assignment_remove', $u, "Rôle {$label} retiré à {$u->username}");

        return back()->with('success', 'Habilitation retirée.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $temp = Str::password(14);
        $user->forceFill(['password' => $temp, 'must_change_password' => true])->save();
        AuditService::log('user.password_reset', $user, "Mot de passe temporaire généré pour {$user->username} (identité vérifiée par l’administrateur)", null, null, $request->input('reason'));

        return back()->with('warning', "Mot de passe temporaire : {$temp} — à remettre en main propre, affiché une seule fois.");
    }

    public function resetTwoFactor(Request $request, User $user)
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
        AuditService::log('user.2fa_reset', $user, "Double authentification réinitialisée pour {$user->username}", null, null, $request->input('reason'));

        return back()->with('success', 'Double authentification réinitialisée : l’utilisateur devra la reconfigurer.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $d = $request->validate(['name' => 'required|string|max:150', 'username' => ['required', 'alpha_dash', 'max:60', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user)], 'phone' => 'nullable|string|max:40',
            'student_matricule' => 'nullable|exists:students,matricule']);
        $d['student_id'] = ! empty($d['student_matricule']) ? Student::query()->where('matricule', $d['student_matricule'])->value('id') : null;
        unset($d['student_matricule']);
        $d['is_active'] = $request->boolean('is_active');

        return $d;
    }
}
