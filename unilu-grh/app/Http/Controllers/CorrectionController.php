<?php

namespace App\Http\Controllers;

use App\Models\CorrectionRequest;
use App\Services\CorrectionService;
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $all = CorrectionRequest::query()->with(['employee', 'handler'])->latest()->limit(200)->get()->filter(fn ($r) => $u->canOnEmployee('corrections.handle', $r->employee));

        return view('corrections.index', ['pending' => $all->where('status', 'pending'), 'done' => $all->where('status', '!=', 'pending')->take(50), 'fields' => CorrectionService::FIELDS]);
    }

    public function handle(Request $request, CorrectionRequest $correction, CorrectionService $svc)
    {
        $data = $request->validate(['decision' => 'required|in:accept,reject', 'response' => 'required|string|max:1000']);
        $svc->handle($correction, $data['decision'] === 'accept', $data['response'], $request->user());

        return back()->with('success', 'Demande traitée ; l’agent est notifié.');
    }
}
