<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()->with('role')
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('username', 'like', "%{$t}%")))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::query()->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        AuditService::log('user.create', $user, "Création de l'utilisateur {$user->username} ({$user->role->name})");

        return redirect()->route('users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'roles' => Role::query()->orderBy('id')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id && ($data['role_id'] != $user->role_id || ! $data['is_active'])) {
            throw new BusinessException('Vous ne pouvez pas modifier votre propre rôle ni désactiver votre propre compte.');
        }
        $this->guardLastAdmin($user, $data);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $old = $user->only(['name', 'username', 'role_id', 'is_active']);
        $user->update($data);
        AuditService::log('user.update', $user, "Modification de l'utilisateur {$user->username}", $old, $user->only(array_keys($old)));

        return redirect()->route('users.index')->with('success', 'Utilisateur modifié.');
    }

    /** Archivage : l'utilisateur ne peut plus se connecter, son historique est conservé. */
    public function archive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw new BusinessException('Vous ne pouvez pas archiver votre propre compte.');
        }
        if (! $user->archived_at) {
            $this->guardLastAdmin($user, ['is_active' => false, 'role_id' => $user->role_id]);
            if ($user->openCashSession()) {
                throw new BusinessException('Cet utilisateur a une caisse ouverte : clôturez-la avant l\'archivage.');
            }
        }
        $user->update(['archived_at' => $user->archived_at ? null : now(), 'is_active' => (bool) $user->archived_at]);
        AuditService::log('user.archive', $user, ($user->archived_at ? 'Archivage' : 'Restauration')." de l'utilisateur {$user->username}");

        return back()->with('success', $user->archived_at ? 'Utilisateur archivé.' : 'Utilisateur restauré.');
    }

    private function guardLastAdmin(User $user, array $data): void
    {
        $adminRoleId = Role::query()->where('slug', Role::ADMIN)->value('id');
        if ($user->role_id === $adminRoleId && ($data['role_id'] != $adminRoleId || ! $data['is_active'])) {
            $others = User::query()->where('role_id', $adminRoleId)->where('is_active', true)->whereNull('archived_at')->where('id', '!=', $user->id)->count();
            if ($others === 0) {
                throw new BusinessException('Impossible : ce compte est le dernier administrateur actif.');
            }
        }
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => ['required', 'alpha_dash', 'max:60', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'phone' => 'nullable|string|max:40',
            'role_id' => 'required|exists:roles,id',
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
