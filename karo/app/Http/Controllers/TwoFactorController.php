<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\QrService;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Second facteur TOTP local (aucune dépendance à Internet), avec codes de récupération à usage unique. */
class TwoFactorController extends Controller
{
    public function __construct(private TotpService $totp) {}

    public function setup(Request $request, QrService $qr)
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return redirect()->route('account.show')->with('info', 'La double authentification est déjà active.');
        }
        $secret = $request->session()->get('2fa_pending') ?? $this->totp->generateSecret();
        $request->session()->put('2fa_pending', $secret);
        $uri = $this->totp->otpauthUri($secret, $user->username, 'Karo '.(setting('university_acronym') ?: 'Université'));

        return view('auth.two-factor-setup', ['secret' => $secret, 'qr' => $qr->dataUri($uri, 200)]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:10']);
        $secret = $request->session()->get('2fa_pending');
        if (! $secret || ! $this->totp->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Code incorrect. Vérifiez l’heure de votre téléphone et réessayez.']);
        }
        $codes = $this->totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();
        $request->session()->forget('2fa_pending');
        $request->session()->put('2fa_passed', true);
        AuditService::log('user.2fa_enabled', $request->user(), 'Activation de la double authentification');

        return view('auth.two-factor-codes', ['codes' => $codes]);
    }

    public function challenge(Request $request)
    {
        if (! $request->user()->hasTwoFactor() || $request->session()->get('2fa_passed')) {
            return redirect()->route('home');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);
        $user = $request->user();
        $code = trim($request->input('code'));
        $ok = $this->totp->verify($user->two_factor_secret, $code);
        if (! $ok && preg_match('/^[A-F0-9]{8}-[A-F0-9]{4}$/i', $code)) {
            $codes = $user->two_factor_recovery_codes ?? [];
            foreach ($codes as $i => $hash) {
                if (Hash::check(strtoupper($code), $hash)) {
                    unset($codes[$i]);   // code de récupération à usage unique
                    $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                    $ok = true;
                    AuditService::log('user.2fa_recovery', $user, 'Connexion avec un code de récupération ('.count($codes).' restant(s))');
                    break;
                }
            }
        }
        if (! $ok) {
            AuditService::log('auth.2fa_failed', $user, 'Code de double authentification incorrect');

            return back()->withErrors(['code' => 'Code incorrect.']);
        }
        $request->session()->regenerate();
        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('home'));
    }
}
