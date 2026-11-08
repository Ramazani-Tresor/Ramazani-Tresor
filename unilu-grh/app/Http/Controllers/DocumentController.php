<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\AuditService;
use App\Services\DocumentStore;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function store(Request $request, Employee $employee, DocumentStore $store)
    {
        $this->authorizeEmployee($employee, 'employees.documents');
        $data = $request->validate([
            'document' => 'required|file|max:'.(Settings::int('max_upload_kb') ?: 10240),
            'title' => 'required|string|max:200', 'document_type_id' => 'nullable|exists:document_types,id',
            'reference' => 'nullable|string|max:120', 'document_date' => 'nullable|date', 'signing_authority' => 'nullable|string|max:150',
        ]);
        $doc = $store->attach($employee, $request->file('document'), $data);
        AuditService::log('documents.upload', $doc, "Pièce « {$doc->title} » ajoutée au dossier {$employee->matricule}");

        return redirect()->to(route('employees.show', $employee).'#documents')->with('success', 'Pièce numérisée ajoutée au dossier.');
    }

    /** Téléchargement contrôlé : gestionnaire habilité sur l'agent, ou l'agent lui-même pour les types autorisés. */
    public function download(Request $request, EmployeeDocument $document)
    {
        $u = $request->user();
        $emp = $document->employee;
        $own = $u->employee_id === $emp->id && $u->hasPermission('self.view') && ($document->type?->visible_to_agent ?? false);
        abort_unless($own || $u->canOnEmployee('employees.view', $emp), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        AuditService::log('documents.download', $document, "Consultation de « {$document->title} » ({$emp->matricule})");
        $name = Str::slug($document->title).'.'.pathinfo($document->path, PATHINFO_EXTENSION);

        return response()->file(Storage::disk('local')->path($document->path), ['Content-Disposition' => 'inline; filename="'.$name.'"', 'Cache-Control' => 'private, no-store']);
    }
}
