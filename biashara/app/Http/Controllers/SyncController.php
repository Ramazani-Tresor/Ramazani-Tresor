<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SyncOperation;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function __construct(private SyncService $sync) {}

    /**
     * Réception d'un lot de ventes hors ligne. Chaque vente est traitée indépendamment ;
     * la réponse donne l'état de chacune (synced | conflict | rejected | invalid).
     */
    public function push(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:64',
            'sales' => 'required|array|min:1|max:50',
        ]);
        $results = [];
        foreach ($request->input('sales') as $payload) {
            $uuid = is_array($payload) ? ($payload['client_uuid'] ?? null) : null;
            $v = Validator::make(is_array($payload) ? $payload : [], PosController::saleRules());
            if ($v->fails()) {
                $results[] = ['client_uuid' => $uuid, 'status' => 'invalid', 'message' => implode(' ', $v->errors()->all()), 'conflicts' => []];

                continue;
            }
            $results[] = ['client_uuid' => $uuid] + $this->sync->pushSale($v->validated(), $request->user(), $request->input('device_id'));
        }

        return response()->json(['results' => $results, 'server_time' => now()->toIso8601String()]);
    }

    /** L'appareil interroge l'état de ses opérations (conflits résolus entre-temps, etc.). */
    public function status(Request $request)
    {
        $data = $request->validate(['uuids' => 'required|array|max:200', 'uuids.*' => 'uuid', 'device_id' => 'nullable|string|max:64']);
        if (! empty($data['device_id'])) {
            $this->sync->touchDevice($data['device_id'], $request->user());
        }
        $ops = SyncOperation::query()->whereIn('client_uuid', $data['uuids'])->get()->keyBy('client_uuid');
        $sales = Sale::query()->whereIn('client_uuid', $data['uuids'])->get()->keyBy('client_uuid');
        $out = [];
        foreach ($data['uuids'] as $uuid) {
            if ($sale = $sales->get($uuid)) {
                $out[$uuid] = ['status' => 'synced', 'sale_number' => $sale->number, 'sale_id' => $sale->id];
            } elseif ($op = $ops->get($uuid)) {
                $out[$uuid] = ['status' => $op->status, 'message' => $op->message, 'conflicts' => $op->conflicts ?? []];
            } else {
                $out[$uuid] = ['status' => 'unknown'];
            }
        }

        return response()->json(['statuses' => $out]);
    }

    public function index(Request $request)
    {
        $operations = SyncOperation::query()->with(['user', 'resolver', 'result'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v), fn ($q) => $q->where('status', 'conflict'))
            ->latest('id')->paginate(20)->withQueryString();

        return view('sync.index', ['operations' => $operations, 'status' => $request->status ?? 'conflict']);
    }

    public function resolve(Request $request, SyncOperation $operation)
    {
        $data = $request->validate(['decision' => 'required|in:accept,reject', 'note' => 'nullable|string|max:255']);
        $op = $this->sync->resolve($operation, $data['decision'], $request->user(), $data['note'] ?? null);

        return back()->with('success', $op->status === 'synced' ? 'Vente acceptée et enregistrée.' : 'Vente rejetée.');
    }
}
