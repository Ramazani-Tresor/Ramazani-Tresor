<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['username' => 'required|string|max:150', 'password' => 'required|string|max:255']);
        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $data['username'], 'password' => $data['password'], 'is_active' => true, 'archived_at' => null])) {
            AuditService::log('auth.failed', null, 'Échec de connexion pour « '.mb_substr($data['username'], 0, 60).' »');

            return back()->withErrors(['username' => 'Identifiant ou mot de passe incorrect.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        AuditService::log('auth.login', $user, 'Connexion');

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        AuditService::log('auth.logout', $request->user(), 'Déconnexion');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function account(Request $request)
    {
        return view('auth.account', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        AuditService::log('user.password', $user, 'Changement de mot de passe');

        return redirect()->route('home')->with('success', 'Mot de passe modifié.');
    }
}
