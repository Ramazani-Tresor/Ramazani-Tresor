<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\ImportBatch;
use App\Models\Promotion;
use App\Models\Student;
use App\Models\User;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Import collectif des étudiants (Excel/CSV) en trois temps :
 *   1. dépôt : fichier source conservé (disque privé) + empreinte SHA-256 ;
 *   2. prévisualisation : association des colonnes, contrôle ligne par ligne (matricule, champs obligatoires,
 *      faculté/promotion, doublons dans le fichier, dossiers existants) ;
 *   3. confirmation : création des dossiers et inscriptions. Un dossier existant différent n'est mis à jour
 *      que si l'option explicite « mettre à jour les dossiers existants » est cochée.
 * Répéter le même import ne crée aucun doublon (matricule unique, une inscription par étudiant et par année).
 */
class StudentImportService
{
    public const FIELDS = [
        'matricule' => ['Matricule', true],
        'last_name' => ['Nom', true],
        'post_name' => ['Postnom', false],
        'first_name' => ['Prénom', true],
        'gender' => ['Sexe (M/F)', false],
        'birth_date' => ['Date de naissance', false],
        'phone' => ['Téléphone', false],
        'email' => ['E-mail', false],
        'address' => ['Adresse', false],
        'faculty_code' => ['Code faculté', true],
        'promotion_code' => ['Code promotion', true],
        'status' => ['Statut (nouveau / ancien / finaliste)', true],
    ];

    private const ALIASES = [
        'matricule' => ['matricule', 'mat', 'numero_matricule', 'id_etudiant'],
        'last_name' => ['nom', 'last_name', 'nom_de_famille'],
        'post_name' => ['postnom', 'post_nom', 'post_name'],
        'first_name' => ['prenom', 'first_name', 'prenoms'],
        'gender' => ['sexe', 'genre', 'gender'],
        'birth_date' => ['date_de_naissance', 'naissance', 'date_naissance', 'birth_date'],
        'phone' => ['telephone', 'tel', 'phone', 'contact'],
        'email' => ['email', 'e_mail', 'courriel', 'mail'],
        'address' => ['adresse', 'address'],
        'faculty_code' => ['faculte', 'code_faculte', 'faculty', 'faculty_code'],
        'promotion_code' => ['promotion', 'code_promotion', 'promotion_code', 'classe'],
        'status' => ['statut', 'status', 'statut_etudiant'],
    ];

    public function __construct(private NumberingService $numbering) {}

    public function upload(UploadedFile $file, int $yearId, User $user): ImportBatch
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            throw new BusinessException('Format non pris en charge : .xlsx ou .csv uniquement.');
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs('imports/students', date('Ymd_His').'_'.$hash.'.'.$ext);
        $rows = SpreadsheetReader::read(Storage::path($path), $ext);
        if (count($rows) < 2) {
            throw new BusinessException('Le fichier est vide ou ne contient que la ligne d’en-têtes.');
        }
        $previous = ImportBatch::query()->where('type', 'students')->where('file_hash', $hash)->where('status', 'completed')->first();
        $batch = ImportBatch::create([
            'number' => $this->numbering->next('import'),
            'type' => 'students',
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_path' => $path,
            'file_hash' => $hash,
            'column_map' => $this->guessMap($rows[0]),
            'options' => ['academic_year_id' => $yearId, 'already_imported_as' => $previous?->number],
            'status' => 'uploaded',
            'rows_total' => count($rows) - 1,
            'created_by' => $user->id,
        ]);
        AuditService::log('import.students.upload', $batch, "Dépôt du fichier {$batch->original_name} ({$batch->rows_total} lignes)");

        return $batch;
    }

    public function headers(ImportBatch $batch): array
    {
        return $this->rows($batch)[0];
    }

    /** @return array{rows: array, summary: array} analyse ligne par ligne, sans aucune écriture */
    public function analyze(ImportBatch $batch, ?array $map = null, bool $updateExisting = false): array
    {
        $map ??= $batch->column_map;
        foreach (self::FIELDS as $field => [$label, $required]) {
            if ($required && (! isset($map[$field]) || $map[$field] === '' || $map[$field] === null)) {
                throw new BusinessException("Associez la colonne obligatoire « {$label} ».");
            }
        }
        $year = AcademicYear::query()->findOrFail($batch->options['academic_year_id']);
        $rows = $this->rows($batch);
        array_shift($rows);
        $faculties = Faculty::query()->get()->keyBy(fn ($f) => mb_strtoupper($f->code));
        $promotions = Promotion::query()->with('program.department')->get()->keyBy(fn ($p) => mb_strtoupper($p->code));
        $seen = [];
        $result = [];
        $summary = ['valid' => 0, 'error' => 0, 'new' => 0, 'update' => 0, 'unchanged' => 0, 'enroll' => 0];

        foreach ($rows as $i => $raw) {
            $line = $i + 2;
            $get = fn ($f) => isset($map[$f]) && $map[$f] !== '' && $map[$f] !== null ? trim((string) ($raw[(int) $map[$f]] ?? '')) : '';
            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $errors = [];
            $d = [];
            foreach (array_keys(self::FIELDS) as $f) {
                $d[$f] = $get($f);
            }
            $d['matricule'] = mb_strtoupper($d['matricule']);
            if ($d['matricule'] === '' || ! preg_match('/^[A-Z0-9][A-Z0-9\/\-.]{2,39}$/', $d['matricule'])) {
                $errors[] = 'Matricule absent ou invalide (3 à 40 caractères : lettres, chiffres, / - .)';
            } elseif (isset($seen[$d['matricule']])) {
                $errors[] = "Matricule en double dans le fichier (ligne {$seen[$d['matricule']]})";
            } else {
                $seen[$d['matricule']] = $line;
            }
            foreach (['last_name' => 'Nom', 'first_name' => 'Prénom'] as $f => $lbl) {
                if ($d[$f] === '') {
                    $errors[] = "{$lbl} obligatoire";
                } elseif (mb_strlen($d[$f]) > 100) {
                    $errors[] = "{$lbl} trop long";
                }
            }
            $faculty = $faculties->get(mb_strtoupper($d['faculty_code']));
            $promotion = $promotions->get(mb_strtoupper($d['promotion_code']));
            if (! $faculty) {
                $errors[] = "Faculté « {$d['faculty_code']} » inconnue";
            }
            if (! $promotion) {
                $errors[] = "Promotion « {$d['promotion_code']} » inconnue";
            } elseif ($faculty && $promotion->program->department->faculty_id !== $faculty->id) {
                $errors[] = "La promotion {$promotion->code} n'appartient pas à la faculté {$faculty->code}";
            }
            $status = $this->parseStatus($d['status']);
            if (! $status) {
                $errors[] = "Statut « {$d['status']} » invalide (nouveau, ancien ou finaliste)";
            } elseif ($status === 'finalist' && $promotion && ! $promotion->is_final) {
                $errors[] = "Statut finaliste incohérent : {$promotion->code} n'est pas une promotion finale";
            }
            if ($d['gender'] !== '') {
                $g = mb_strtoupper(mb_substr($d['gender'], 0, 1));
                $d['gender'] = in_array($g, ['M', 'H'], true) ? 'M' : ($g === 'F' ? 'F' : null);
                if (! $d['gender']) {
                    $errors[] = 'Sexe invalide (M ou F)';
                }
            } else {
                $d['gender'] = null;
            }
            if ($d['birth_date'] !== '') {
                $d['birth_date'] = SpreadsheetReader::parseDate($d['birth_date']);
                if (! $d['birth_date']) {
                    $errors[] = 'Date de naissance invalide';
                }
            } else {
                $d['birth_date'] = null;
            }
            if ($d['email'] !== '' && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'E-mail invalide';
            }

            $action = null;
            $notes = [];
            if (! $errors) {
                $existing = Student::query()->where('matricule', $d['matricule'])->first();
                if ($existing) {
                    $changes = [];
                    foreach (['last_name', 'post_name', 'first_name', 'gender', 'phone', 'email', 'address'] as $f) {
                        if ((string) $existing->{$f} !== (string) ($d[$f] ?: null) && $d[$f] !== '' && $d[$f] !== null) {
                            $changes[] = $f;
                        }
                    }
                    if ($changes && ! $updateExisting) {
                        $action = 'skip_existing';
                        $notes[] = 'Dossier existant différent ('.implode(', ', $changes).') : non modifié sans l’option de mise à jour';
                    } else {
                        $action = $changes ? 'update' : 'unchanged';
                    }
                    $enrollment = $existing->enrollments()->where('academic_year_id', $year->id)->first();
                    if ($enrollment && $promotion && $enrollment->promotion_id !== $promotion->id) {
                        if (! $updateExisting) {
                            $errors[] = "Déjà inscrit en {$year->code} dans une autre promotion ({$enrollment->promotion->code})";
                        } else {
                            $notes[] = 'Changement de promotion de l’inscription';
                        }
                    } elseif (! $enrollment) {
                        $notes[] = "Inscription {$year->code} à créer";
                    }
                } else {
                    $action = 'create';
                }
            }
            $ok = ! $errors;
            $summary[$ok ? 'valid' : 'error']++;
            if ($ok) {
                $summary[match ($action) {
                    'create' => 'new', 'update' => 'update', default => 'unchanged'
                }]++;
            }
            $result[] = ['line' => $line, 'data' => $d, 'status' => $status, 'promotion_id' => $promotion?->id, 'errors' => $errors, 'notes' => $notes, 'action' => $action];
        }

        return ['rows' => $result, 'summary' => $summary];
    }

    public function preview(ImportBatch $batch, array $map, bool $updateExisting): array
    {
        $analysis = $this->analyze($batch, $map, $updateExisting);
        $batch->update([
            'column_map' => $map,
            'options' => array_merge($batch->options, ['update_existing' => $updateExisting]),
            'status' => 'previewed',
            'rows_valid' => $analysis['summary']['valid'],
            'rows_error' => $analysis['summary']['error'],
            'report' => array_values(array_map(fn ($r) => ['line' => $r['line'], 'matricule' => $r['data']['matricule'], 'errors' => $r['errors'], 'notes' => $r['notes'], 'action' => $r['action']], $analysis['rows'])),
        ]);

        return $analysis;
    }

    /** Confirmation : écriture atomique. Si des erreurs existent, il faut choisir explicitement d'ignorer ces lignes. */
    public function confirm(ImportBatch $batch, User $user, bool $skipInvalid, bool $generateCharges): ImportBatch
    {
        if (! in_array($batch->status, ['previewed'], true)) {
            throw new BusinessException('Prévisualisez l’import avant de le confirmer (ou cet import a déjà été traité).');
        }
        $year = YearGuard::assertWritable((int) $batch->options['academic_year_id']);
        $updateExisting = (bool) ($batch->options['update_existing'] ?? false);
        $analysis = $this->analyze($batch, $batch->column_map, $updateExisting);
        if ($analysis['summary']['error'] > 0 && ! $skipInvalid) {
            throw new BusinessException("{$analysis['summary']['error']} ligne(s) en erreur : corrigez le fichier ou confirmez explicitement l'import des seules lignes valides.");
        }

        $stats = DB::transaction(function () use ($batch, $analysis, $updateExisting, $year, $user, $generateCharges) {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'previewed') {
                throw new BusinessException('Cet import a déjà été traité.');
            }
            $batch->update(['status' => 'processing']);
            $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            $charges = app(ChargeService::class);
            foreach ($analysis['rows'] as $r) {
                if ($r['errors']) {
                    $stats['skipped']++;

                    continue;
                }
                $d = $r['data'];
                $attrs = array_filter([
                    'last_name' => $d['last_name'], 'post_name' => $d['post_name'] ?: null, 'first_name' => $d['first_name'],
                    'gender' => $d['gender'], 'birth_date' => $d['birth_date'], 'phone' => $d['phone'] ?: null,
                    'email' => $d['email'] ?: null, 'address' => $d['address'] ?: null,
                ], fn ($v) => $v !== null);
                $student = Student::query()->where('matricule', $d['matricule'])->lockForUpdate()->first();
                if (! $student) {
                    $student = Student::create($attrs + ['matricule' => $d['matricule'], 'is_active' => true]);
                    $stats['created']++;
                } elseif ($r['action'] === 'update' && $updateExisting) {
                    $old = $student->only(array_keys($attrs));
                    $student->update($attrs);
                    AuditService::log('student.update_import', $student, "Mise à jour par import {$batch->number}", $old, $attrs);
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
                $enrollment = Enrollment::query()->where('student_id', $student->id)->where('academic_year_id', $year->id)->first();
                if (! $enrollment) {
                    $enrollment = Enrollment::create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'promotion_id' => $r['promotion_id'],
                        'status' => $r['status'], 'state' => 'active', 'enrolled_on' => now()->toDateString(), 'import_batch_id' => $batch->id]);
                } elseif ($updateExisting && ($enrollment->promotion_id !== $r['promotion_id'] || $enrollment->status !== $r['status'])) {
                    if ($enrollment->promotion_id !== $r['promotion_id'] && Charge::query()->where('enrollment_id', $enrollment->id)->exists()) {
                        throw new BusinessException("Ligne {$r['line']} : changement de promotion impossible, des créances existent déjà pour cette inscription. Traitez-le dans le dossier.");
                    }
                    $enrollment->update(['promotion_id' => $r['promotion_id'], 'status' => $r['status']]);
                }
                if ($generateCharges) {
                    $charges->generateForEnrollment($enrollment->fresh(), $user);
                }
            }
            $batch->update(['status' => 'completed', 'rows_created' => $stats['created'], 'rows_updated' => $stats['updated'], 'rows_skipped' => $stats['skipped']]);

            return $stats;
        });
        AuditService::log('import.students.confirm', $batch, "Import {$batch->number} : {$stats['created']} créé(s), {$stats['updated']} mis à jour, {$stats['skipped']} ignoré(s)");

        return $batch->fresh();
    }

    private function rows(ImportBatch $batch): array
    {
        return SpreadsheetReader::read(Storage::path($batch->stored_path), pathinfo($batch->stored_path, PATHINFO_EXTENSION));
    }

    private function guessMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $n = SpreadsheetReader::normalizeHeader((string) $h);
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($n, $aliases, true)) {
                    $map[$field] = $idx;
                }
            }
        }

        return $map;
    }

    private function parseStatus(string $v): ?string
    {
        $n = SpreadsheetReader::normalizeHeader($v);

        return match (true) {
            in_array($n, ['nouveau', 'nouvel_inscrit', 'new', 'n', 'nouveau_inscrit'], true) => 'new',
            in_array($n, ['ancien', 'ancien_etudiant', 'returning', 'a', 'reinscrit'], true) => 'returning',
            in_array($n, ['finaliste', 'finalist', 'f'], true) => 'finalist',
            default => null,
        };
    }
}
