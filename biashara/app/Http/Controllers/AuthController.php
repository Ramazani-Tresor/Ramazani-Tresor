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
        $credentials = $request->validate([
            'username' => 'required|string|max:150',
            'password' => 'required|string|max:255',
        ]);
        $field = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $credentials['username'], 'password' => $credentials['password'], 'is_active' => true, 'archived_at' => null], $request->boolean('remember'))) {
            AuditService::log('auth.failed', null, 'Échec de connexion pour « '.$credentials['username'].' »');

            return back()->withErrors(['username' => __('auth.failed')])->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        AuditService::log('auth.login', $request->user(), 'Connexion');

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
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }
        $user->update(['password' => $data['password']]);
        AuditService::log('user.password', $user, 'Changement de mot de passe');

        return back()->with('success', 'Mot de passe modifié.');
    }
}
