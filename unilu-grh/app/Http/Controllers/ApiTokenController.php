<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return view('tokens.index', ['tokens' => ApiToken::query()->with('user')->latest()->get(), 'users' => User::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['user_id' => 'required|exists:users,id', 'name' => 'required|string|max:100', 'expires_at' => 'nullable|date|after:today']);
        $plain = 'ugrh_'.Str::random(48);
        $t = ApiToken::create(['user_id' => $data['user_id'], 'name' => $data['name'], 'token_hash' => hash('sha256', $plain), 'expires_at' => $data['expires_at'] ?? null]);
        AuditService::log('api.token_create', $t, "Création du jeton « {$t->name} »");

        return back()->with('success', "Jeton créé. Copiez-le maintenant, il ne sera plus affiché : {$plain}");
    }

    public function revoke(ApiToken $token)
    {
        $token->update(['revoked_at' => now()]);
        AuditService::log('api.token_revoke', $token, "Révocation du jeton « {$token->name} »");

        return back()->with('success', 'Jeton révoqué.');
    }
}
