<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Services\DocumentStore;
use App\Support\Settings;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function user()
    {
        return request()->user();
    }

    /** Contrôle d'accès à un agent précis (périmètre unité/famille) : protège contre la manipulation d'identifiants. */
    protected function authorizeEmployee(Employee $employee, string $permission): void
    {
        abort_unless($this->user()->canOnEmployee($permission, $employee), 403);
    }

    /** Pièce justificative facultative jointe à un formulaire (champ « document ») ; renvoie l'identifiant du document. */
    protected function attachDocument(Request $request, Employee $employee, string $defaultTitle, ?string $reference = null, ?string $authority = null, ?string $typeCode = null): ?int
    {
        if (! $request->hasFile('document')) {
            return null;
        }
        $typeId = $typeCode ? DocumentType::query()->where('code', $typeCode)->value('id') : null;

        return app(DocumentStore::class)->attach($employee, $request->file('document'), [
            'title' => $request->input('document_title') ?: $defaultTitle, 'reference' => $reference,
            'document_date' => $request->input('decision_date') ?: now()->toDateString(), 'signing_authority' => $authority,
            'document_type_id' => $typeId,
        ])->id;
    }

    protected function docRules(): array
    {
        return ['document' => 'nullable|file|max:'.(Settings::int('max_upload_kb') ?: 10240), 'document_title' => 'nullable|string|max:200'];
    }
}
