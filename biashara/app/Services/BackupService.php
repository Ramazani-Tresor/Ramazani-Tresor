<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Support\Settings;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Sauvegardes de la base dans storage/app/backups (dossier privé, non exposé au web).
 * MySQL : mysqldump (chemin configurable, ex. C:\xampp\mysql\bin\mysqldump.exe).
 * Le mot de passe est transmis via la variable d'environnement MYSQL_PWD, jamais en argument visible.
 */
class BackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);

        return $dir;
    }

    public function create(): string
    {
        $connection = config('database.default');
        $cfg = config("database.connections.{$connection}");
        $stamp = now()->format('Ymd_His');

        if ($cfg['driver'] === 'sqlite') {
            $file = "sauvegarde_{$stamp}.sqlite";
            File::copy($cfg['database'], $this->directory().DIRECTORY_SEPARATOR.$file);

            return $file;
        }
        if (! in_array($cfg['driver'], ['mysql', 'mariadb'], true)) {
            throw new BusinessException('Sauvegarde automatique disponible uniquement pour MySQL/MariaDB.');
        }

        $binary = Settings::get('backup_mysqldump_path') ?: 'mysqldump';
        if (str_contains($binary, DIRECTORY_SEPARATOR) && ! is_file($binary)) {
            $binary = 'mysqldump';
        }
        $file = "sauvegarde_{$stamp}.sql";
        $target = $this->directory().DIRECTORY_SEPARATOR.$file;
        $process = new Process([
            $binary, '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'],
            '--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file='.$target, $cfg['database'],
        ], null, ['MYSQL_PWD' => (string) $cfg['password']]);
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful()) {
            @unlink($target);
            throw new BusinessException('Échec de la sauvegarde : vérifiez le chemin de mysqldump dans les paramètres. '.trim(substr($process->getErrorOutput(), 0, 200)));
        }

        return $file;
    }

    public function list(): array
    {
        return collect(File::files($this->directory()))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'date' => date('d/m/Y H:i', $f->getMTime())])
            ->values()->all();
    }

    public function path(string $file): string
    {
        if (! preg_match('/^sauvegarde_\d{8}_\d{6}\.(sql|sqlite)$/', $file)) {
            abort(404);
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$file;
        abort_unless(is_file($path), 404);

        return $path;
    }
}
