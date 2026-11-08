<?php

use App\Services\AlertService;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('grh:backup-key {--show : afficher sans écrire dans .env}', function () {
    $key = base64_encode(random_bytes(32));
    if ($this->option('show')) {
        $this->line($key);

        return;
    }
    $env = base_path('.env');
    if (! File::exists($env)) {
        $this->error('.env introuvable.');

        return 1;
    }
    $content = File::get($env);
    if (preg_match('/^BACKUP_ENCRYPTION_KEY=.+$/m', $content)) {
        $this->warn('Une clé existe déjà : elle n’est pas remplacée (les anciennes sauvegardes deviendraient illisibles).');

        return 1;
    }
    $content = preg_match('/^BACKUP_ENCRYPTION_KEY=$/m', $content) ? preg_replace('/^BACKUP_ENCRYPTION_KEY=$/m', 'BACKUP_ENCRYPTION_KEY='.$key, $content) : $content.PHP_EOL.'BACKUP_ENCRYPTION_KEY='.$key.PHP_EOL;
    File::put($env, $content);
    $this->info('Clé de sauvegarde générée. Conservez-en une copie HORS du serveur.');
})->purpose('Générer la clé de chiffrement des sauvegardes');

Artisan::command('grh:backup', function (BackupService $backups) {
    $r = $backups->create();
    $this->info("Sauvegarde créée : {$r['name']}");
    foreach ($r['copies'] as $c) {
        $this->line(' - '.$c);
    }
    $this->line('SHA-256 : '.$r['sha256']);
    $purged = $backups->purge((int) config('grh.backup.keep_days', 30));
    if ($purged) {
        $this->line("{$purged} sauvegarde(s) de plus de ".config('grh.backup.keep_days').' jours supprimée(s) de l’emplacement principal.');
    }
})->purpose('Créer une sauvegarde chiffrée (base + documents) avec copie séparée');

Artisan::command('grh:restore {file} {--force}', function (BackupService $backups, string $file) {
    if (! $this->option('force') && ! $this->confirm('La base et les documents actuels seront remplacés. Continuer ?')) {
        return 1;
    }
    $backups->restore($file);
    $this->info('Restauration terminée.');
})->purpose('Restaurer une sauvegarde chiffrée');

Artisan::command('grh:audit-verify', function () {
    $r = AuditService::verifyChain();
    if ($r['ok']) {
        $this->info("Journal intègre ({$r['checked']} entrées vérifiées).");

        return 0;
    }
    $this->error("Chaîne rompue à l’entrée #{$r['broken_at']} (après {$r['checked']} entrées valides).");

    return 1;
})->purpose('Vérifier l’intégrité du journal d’audit chaîné');

Artisan::command('grh:alerts', function (AlertService $alerts) {
    $r = $alerts->run();
    $this->info("Alertes : {$r['mandates']} mandat(s), {$r['situations']} situation(s) ; {$r['started']} situation(s) entrée(s) en vigueur.");
})->purpose('Alertes d’échéance (mandats, congés et situations) et application des situations débutant ce jour');

Schedule::command('grh:alerts')->dailyAt('06:15');
Schedule::command('grh:backup')->dailyAt('22:30');
Schedule::command('grh:audit-verify')->weeklyOn(0, '23:00');
