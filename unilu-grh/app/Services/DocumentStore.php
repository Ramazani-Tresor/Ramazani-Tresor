<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Stockage privé des pièces numérisées (disque « local » = storage/app/private, jamais exposé publiquement).
 * Type réel vérifié par finfo, taille bornée, nom de fichier = empreinte SHA-256.
 */
class DocumentStore
{
    public const ALLOWED = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function validateFile(UploadedFile $file, array $allowed = self::ALLOWED): array
    {
        if (! $file->isValid()) {
            throw new BusinessException('Le fichier n’a pas pu être reçu.');
        }
        $max = Settings::int('max_upload_kb') ?: 10240;
        if ($file->getSize() > $max * 1024) {
            throw new BusinessException("Fichier trop volumineux (maximum {$max} Ko).");
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset($allowed[$mime])) {
            throw new BusinessException('Type de fichier non autorisé (PDF, JPG ou PNG uniquement).');
        }

        return [$mime, $allowed[$mime], hash_file('sha256', $file->getRealPath())];
    }

    public function storeRaw(UploadedFile $file, string $folder, array $allowed = self::ALLOWED): array
    {
        [$mime, $ext, $sha] = $this->validateFile($file, $allowed);
        $path = $file->storeAs($folder.'/'.date('Y/m'), $sha.'.'.$ext, 'local');

        return ['path' => $path, 'mime' => $mime, 'size' => $file->getSize(), 'sha256' => $sha];
    }

    public function attach(Employee $employee, UploadedFile $file, array $meta, ?Model $related = null): EmployeeDocument
    {
        $raw = $this->storeRaw($file, 'dossiers/'.$employee->id);

        return EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type_id' => $meta['document_type_id'] ?? null,
            'title' => $meta['title'] ?? $file->getClientOriginalName(),
            'reference' => $meta['reference'] ?? null,
            'document_date' => $meta['document_date'] ?? null,
            'signing_authority' => $meta['signing_authority'] ?? null,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'uploaded_by' => Auth::id(),
        ] + $raw);
    }

    public function exists(string $path): bool
    {
        return Storage::disk('local')->exists($path);
    }
}
