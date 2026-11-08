<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['username' => 'required|string|max:150', 'password' => 'required|string|max:255']);
        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $data['username'], 'password' => $data['password'], 'is_active' => true, 'archived_at' => null])) {
            return back()->withInput($request->only('username'))->with('error', 'Identifiants incorrects ou compte désactivé.');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function passwordForm()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ]);
        $user = $request->user();
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->with('error', 'Le nouveau mot de passe doit être différent de l’actuel.');
        }
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();
        Auth::logoutOtherDevices($request->input('password'));
        AuditService::log('auth.password', $user, 'Changement de mot de passe');

        return redirect()->route('home')->with('success', 'Mot de passe modifié.');
    }
}
