<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

abstract class Controller
{
    protected function user()
    {
        return request()->user();
    }

    /** Année sélectionnée (paramètre ?year=) ou année courante. */
    protected function year(Request $request): ?AcademicYear
    {
        return $request->filled('year') ? AcademicYear::query()->find($request->integer('year')) : AcademicYear::current();
    }

    protected function authorizeStudent(Student $student, string $permission): void
    {
        abort_unless($this->user()->canAccessStudent($student, $permission), 403);
    }

    protected function period(Request $request, int $defaultDays = 30): array
    {
        $from = $request->date('from') ?? now()->subDays($defaultDays);
        $to = $request->date('to') ?? now();

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }

    /**
     * Stockage privé d'un justificatif : type réel vérifié (finfo), taille limitée, nom = empreinte SHA-256.
     * Le fichier n'est jamais servi directement : téléchargement via contrôleur avec contrôle d'accès.
     */
    protected function storePrivateFile(UploadedFile $file, string $folder, array $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png']): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset($allowed[$mime])) {
            throw new BusinessException('Type de fichier non autorisé ('.implode(', ', array_map('strtoupper', array_unique($allowed))).').');
        }
        $sha = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs($folder.'/'.date('Y/m'), $sha.'.'.$allowed[$mime]);

        return ['path' => $path, 'sha256' => $sha, 'mime' => $mime, 'size' => $file->getSize()];
    }
}
