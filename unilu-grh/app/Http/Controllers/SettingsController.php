<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const FIELDS = [
        'university_name' => ['Nom de l’université', 'text'], 'university_acronym' => ['Sigle', 'text'], 'university_motto' => ['Devise', 'text'],
        'university_address' => ['Adresse', 'text'], 'university_phone' => ['Téléphone', 'text'], 'university_email' => ['Courriel', 'email'],
        'country_header' => ['En-tête (pays)', 'text'], 'ministry_header' => ['En-tête (ministère)', 'text'],
        'signatory_1_title' => ['Signataire 1 — qualité', 'text'], 'signatory_1_name' => ['Signataire 1 — nom', 'text'],
        'signatory_2_title' => ['Signataire 2 — qualité', 'text'], 'signatory_2_name' => ['Signataire 2 — nom', 'text'],
        'default_currency' => ['Devise par défaut', 'text'],
        'segregation_of_duties' => ['Séparation des tâches (validation par une autre personne)', 'bool'],
        'multiple_primary_assignments' => ['Autoriser plusieurs affectations principales actives', 'bool'],
        'require_decision_document' => ['Pièce numérisée obligatoire pour valider une décision', 'bool'],
        'self_service_enabled' => ['Espace personnel des agents activé', 'bool'],
        'max_upload_kb' => ['Taille maximale des pièces (Ko)', 'number'],
    ];

    public function index()
    {
        return view('settings.index', ['fields' => self::FIELDS, 'refs' => ReferenceController::REFS]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::FIELDS as $k => [$l, $t]) {
            $rules[$k] = match ($t) {
                'bool' => 'nullable|boolean', 'number' => 'nullable|integer|min:256|max:51200', 'email' => 'nullable|email|max:150', default => 'nullable|string|max:255'
            };
        }
        $data = $request->validate($rules);
        foreach (self::FIELDS as $k => [$l, $t]) {
            if ($t === 'bool') {
                $data[$k] = $request->boolean($k) ? '1' : '0';
            }
        }
        $old = array_intersect_key(Settings::all(), $data);
        Settings::set($data);
        AuditService::log('settings.update', null, 'Mise à jour des paramètres institutionnels', $old, $data);

        return back()->with('success', 'Paramètres enregistrés.');
    }
}
