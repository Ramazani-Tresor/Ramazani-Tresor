<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\User;
use App\Services\CashService;
use App\Support\Settings;
use Illuminate\Http\Request;

class CashController extends Controller
{
    public function __construct(private CashService $cash) {}

    /** Ma caisse : session ouverte, opérations, clôture. */
    public function index(Request $request)
    {
        $session = $request->user()->openCashSession();
        if ($session) {
            return redirect()->route('cash.show', $session);
        }

        return view('cash.open', [
            'lastSessions' => CashSession::query()->where('user_id', $request->user()->id)->latest('id')->limit(5)->get(),
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate(['opening_amount' => 'required|numeric|min:0']);
        $session = $this->cash->open($request->user(), (string) $data['opening_amount']);

        return redirect()->route('cash.show', $session)->with('success', 'Caisse ouverte.');
    }

    public function movement(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|gt:0',
            'description' => 'required|string|max:255',
        ]);
        $this->cash->manual($request->user(), $data['type'], (string) $data['amount'], $data['description']);

        return back()->with('success', 'Opération de caisse enregistrée.');
    }

    public function sessions(Request $request)
    {
        $this->authorizeAny(['cash.use', 'cash.view_all']);
        $all = $request->user()->hasPermission('cash.view_all');
        $sessions = CashSession::query()->with(['user', 'closer'])
            ->when(! $all, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($all && $request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate(25)->withQueryString();

        return view('cash.sessions', [
            'sessions' => $sessions,
            'users' => $all ? User::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'expected' => $sessions->getCollection()->where('status', 'open')->mapWithKeys(fn ($s) => [$s->id => $this->cash->expectedCash($s)]),
        ]);
    }

    public function show(Request $request, CashSession $session)
    {
        $this->authorizeSession($request, $session);

        return view('cash.show', [
            'session' => $session->load(['user', 'closer']),
            'movements' => $session->movements()->with(['paymentMethod', 'user'])->latest('id')->paginate(50),
            'expected' => $this->cash->expectedCash($session),
            'electronic' => $this->cash->electronicTotal($session),
            'byMethod' => $this->cash->totalsByMethod($session),
            'canClose' => $session->isOpen() && ($session->user_id === $request->user()->id || $request->user()->hasPermission('cash.close_any')),
            'isMine' => $session->user_id === $request->user()->id,
            'shop' => Settings::all(),
            'print' => $request->boolean('print'),
        ]);
    }

    public function close(Request $request, CashSession $session)
    {
        $this->authorizeSession($request, $session);
        abort_unless($session->user_id === $request->user()->id || $request->user()->hasPermission('cash.close_any'), 403);
        $data = $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string|max:1000',
        ]);
        $this->cash->close($session, (string) $data['counted_cash'], $data['closing_notes'] ?? null, $request->user());

        return redirect()->route('cash.show', [$session, 'print' => 1])->with('success', 'Caisse clôturée. Le rapport de clôture est prêt à imprimer.');
    }

    private function authorizeSession(Request $request, CashSession $session): void
    {
        $user = $request->user();
        abort_unless($session->user_id === $user->id || $user->hasPermission('cash.view_all'), 403);
    }
}
