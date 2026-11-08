<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Sauvegardes CHIFFRÉES (AES-256-GCM, clé BACKUP_ENCRYPTION_KEY hors du code et de la base),
 * avec copie automatique dans un second emplacement (BACKUP_SECONDARY_PATH : disque externe, NAS…).
 * Format : "UGRH1" | IV (12 o) | TAG (16 o) | données chiffrées. Les justificatifs (storage/app/private)
 * sont inclus dans l'archive. Restauration : déchiffrement, vérification d'intégrité (tag GCM), import.
 */
class BackupService
{
    private const MAGIC = 'UGRH1';

    public function directory(): string
    {
        $dir = config('grh.backup.path');
        File::ensureDirectoryExists($dir, 0750);

        return $dir;
    }

    public function create(): array
    {
        $key = $this->key();
        $tmp = storage_path('app/backup_'.uniqid().'.tmp');
        $this->dump($tmp);
        $zipPath = $tmp.'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($tmp, 'database.dump');
        $private = storage_path('app/private');
        if (is_dir($private)) {
            foreach (File::allFiles($private) as $f) {
                if (! str_starts_with($f->getRelativePathname(), 'backups')) {
                    $zip->addFile($f->getPathname(), 'private/'.$f->getRelativePathname());
                }
            }
        }
        $zip->addFromString('manifest.json', json_encode(['created_at' => now()->toIso8601String(), 'driver' => DB::getDriverName(), 'app' => 'unilu-grh'], JSON_PRETTY_PRINT));
        $zip->close();
        $plain = file_get_contents($zipPath);
        @unlink($tmp);
        @unlink($zipPath);

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        $name = 'grh_'.now()->format('Ymd_His').'.kbk';
        $target = $this->directory().DIRECTORY_SEPARATOR.$name;
        file_put_contents($target, self::MAGIC.$iv.$tag.$cipher);
        $copies = [$target];
        if ($secondary = config('grh.backup.secondary_path')) {
            File::ensureDirectoryExists($secondary, 0750);
            File::copy($target, rtrim($secondary, '/\\').DIRECTORY_SEPARATOR.$name);
            $copies[] = rtrim($secondary, '/\\').DIRECTORY_SEPARATOR.$name;
        }
        AuditService::log('backup.create', null, "Sauvegarde chiffrée {$name} (".count($copies).' copie(s))');

        return ['name' => $name, 'copies' => $copies, 'sha256' => hash_file('sha256', $target)];
    }

    public function restore(string $file): void
    {
        $data = @file_get_contents($file);
        if ($data === false || ! str_starts_with($data, self::MAGIC)) {
            throw new BusinessException('Fichier de sauvegarde invalide.');
        }
        $iv = substr($data, 5, 12);
        $tag = substr($data, 17, 16);
        $plain = openssl_decrypt(substr($data, 33), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new BusinessException('Déchiffrement impossible : clé incorrecte ou fichier altéré.');
        }
        $zipPath = storage_path('app/restore_'.uniqid().'.zip');
        file_put_contents($zipPath, $plain);
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new BusinessException('Archive corrompue.');
        }
        $dir = storage_path('app/restore_'.uniqid());
        $zip->extractTo($dir);
        $zip->close();
        @unlink($zipPath);
        $this->load($dir.'/database.dump');
        if (is_dir($dir.'/private')) {
            File::copyDirectory($dir.'/private', storage_path('app/private'));
        }
        File::deleteDirectory($dir);
    }

    /** Supprime de l'emplacement principal les sauvegardes plus anciennes que N jours (la copie séparée n'est jamais purgée ici). */
    public function purge(int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }
        $n = 0;
        foreach (glob($this->directory().DIRECTORY_SEPARATOR.'grh_*.kbk') ?: [] as $f) {
            if (filemtime($f) < now()->subDays($keepDays)->getTimestamp()) {
                @unlink($f);
                $n++;
            }
        }
        if ($n) {
            AuditService::log('backup.purge', null, "{$n} sauvegarde(s) ancienne(s) purgée(s) de l’emplacement principal");
        }

        return $n;
    }

    public function list(): array
    {
        return collect(File::files($this->directory()))->filter(fn ($f) => $f->getExtension() === 'kbk')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'date' => date('d/m/Y H:i', $f->getMTime())])->values()->all();
    }

    public function path(string $name): string
    {
        abort_unless(preg_match('/^grh_\d{8}_\d{6}\.kbk$/', $name), 404);
        $p = $this->directory().DIRECTORY_SEPARATOR.$name;
        abort_unless(is_file($p), 404);

        return $p;
    }

    private function key(): string
    {
        $raw = (string) config('grh.backup.key');
        // Formats acceptés : « base64:… », base64 brut (sortie de grh:backup-key) ou 32 octets bruts.
        $b64 = str_starts_with($raw, 'base64:') ? substr($raw, 7) : $raw;
        $decoded = base64_decode($b64, true);
        $key = $decoded !== false && strlen($decoded) === 32 ? $decoded : $raw;
        if (! $key || strlen($key) !== 32) {
            throw new BusinessException('Clé de chiffrement des sauvegardes absente ou invalide (BACKUP_ENCRYPTION_KEY, 32 octets en base64). Voir la documentation.');
        }

        return $key;
    }

    private function dump(string $target): void
    {
        $cfg = config('database.connections.'.config('database.default'));
        if ($cfg['driver'] === 'sqlite') {
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($target));

            return;
        }
        $process = new Process([config('grh.backup.mysqldump'), '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'],
            '--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file='.$target, $cfg['database']], null, ['MYSQL_PWD' => (string) $cfg['password']]);
        $process->setTimeout(1800)->run();
        if (! $process->isSuccessful()) {
            throw new BusinessException('Échec de mysqldump : '.mb_substr($process->getErrorOutput(), 0, 200));
        }
    }

    private function load(string $dump): void
    {
        $name = config('database.default');
        $cfg = config("database.connections.{$name}");
        if ($cfg['driver'] === 'sqlite') {
            DB::disconnect($name);
            File::copy($dump, $cfg['database']);
            DB::reconnect($name);

            return;
        }
        $process = Process::fromShellCommandline('"${:BIN}" --host="${:HOST}" --port="${:PORT}" --user="${:USER}" "${:DB}" < "${:FILE}"', null, ['MYSQL_PWD' => (string) $cfg['password']]);
        $process->setTimeout(1800)->run(null, ['BIN' => config('grh.backup.mysql'), 'HOST' => $cfg['host'], 'PORT' => $cfg['port'], 'USER' => $cfg['username'], 'DB' => $cfg['database'], 'FILE' => $dump]);
        if (! $process->isSuccessful()) {
            throw new BusinessException('Échec de la restauration MySQL : '.mb_substr($process->getErrorOutput(), 0, 200));
        }
    }
}
