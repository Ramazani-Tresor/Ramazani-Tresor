<?php

namespace App\Http\Controllers;

use App\Models\Death;
use App\Models\Employee;
use App\Services\DeathService;
use Illuminate\Http\Request;

class DeathController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $deaths = Death::query()->with(['employee.grade', 'author', 'validator'])->latest('death_date')->get()
            ->filter(fn ($d) => $u->canOnEmployee('deaths.manage', $d->employee) || $u->canOnEmployee('deaths.validate', $d->employee));

        return view('deaths.index', ['deaths' => $deaths, 'canCause' => $u->hasPermission('deaths.cause')]);
    }

    public function store(Request $request, Employee $employee, DeathService $svc)
    {
        $this->authorizeEmployee($employee, 'deaths.manage');
        $data = $request->validate([
            'death_date' => 'required|date|before_or_equal:today', 'death_place' => 'nullable|string|max:150', 'cause' => 'nullable|string|max:500',
            'contact_name' => 'nullable|string|max:150', 'contact_phone' => 'nullable|string|max:40', 'contact_relation' => 'nullable|string|max:80',
        ] + $this->docRules());
        $data['document_id'] = $this->attachDocument($request, $employee, 'Document officiel de constatation du décès', null, null, 'DECES');
        $svc->record($employee, $data, $request->user());

        return redirect()->route('deaths.index')->with('success', 'Décès enregistré : les effets seront appliqués à sa validation par une autre personne habilitée.');
    }

    public function validateDeath(Request $request, Death $death, DeathService $svc)
    {
        $svc->validate($death, $request->user());

        return back()->with('success', 'Décès validé : statut « Décédé », affectation clôturée, primes arrêtées à la date d’effet, soutien social ouvert. Le dossier est conservé.');
    }
}
