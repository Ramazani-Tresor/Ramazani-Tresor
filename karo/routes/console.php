<?php

use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('karo:backup-key {--show : afficher sans écrire dans .env}', function () {
    $key = 'base64:'.base64_encode(random_bytes(32));
    if ($this->option('show')) {
        $this->line($key);

        return 0;
    }
    $env = base_path('.env');
    $content = file_exists($env) ? file_get_contents($env) : '';
    if (preg_match('/^BACKUP_ENCRYPTION_KEY=.+$/m', $content)) {
        $this->error('Une clé existe déjà : ne la remplacez pas, sinon les sauvegardes existantes deviendraient illisibles.');

        return 1;
    }
    $content = preg_match('/^BACKUP_ENCRYPTION_KEY=$/m', $content) ? preg_replace('/^BACKUP_ENCRYPTION_KEY=$/m', 'BACKUP_ENCRYPTION_KEY='.$key, $content) : $content."\nBACKUP_ENCRYPTION_KEY={$key}\n";
    file_put_contents($env, $content);
    $this->info('Clé de chiffrement des sauvegardes générée dans .env. CONSERVEZ-EN UNE COPIE HORS DU SERVEUR (coffre, papier scellé) :');
    $this->line($key);

    return 0;
})->purpose('Générer la clé de chiffrement des sauvegardes');

Artisan::command('karo:backup', function (BackupService $backups) {
    $b = $backups->create();
    $this->info("Sauvegarde chiffrée : {$b['name']}");
    foreach ($b['copies'] as $c) {
        $this->line(' - '.$c);
    }
    $this->line('SHA-256 : '.$b['sha256']);
})->purpose('Créer une sauvegarde chiffrée (base + justificatifs) avec copie séparée');

Artisan::command('karo:restore {file} {--force}', function (BackupService $backups, string $file) {
    if (! $this->option('force') && ! $this->confirm("Restaurer {$file} ? Les données actuelles seront REMPLACÉES.")) {
        return 1;
    }
    Artisan::call('down');
    try {
        $backups->restore($file);
        Artisan::call('optimize:clear');
        $this->info('Restauration terminée. Vérifiez l’intégrité : php artisan karo:audit-verify');
    } finally {
        Artisan::call('up');
    }

    return 0;
})->purpose('Restaurer une sauvegarde chiffrée');

Artisan::command('karo:audit-verify', function () {
    $r = AuditService::verifyChain();
    if ($r['ok']) {
        $this->info("Journal d’audit intègre ({$r['checked']} entrées).");

        return 0;
    }
    $this->error("Chaîne rompue à l’entrée #{$r['broken_at']} : le journal a été modifié hors de l’application.");

    return 1;
})->purpose('Vérifier l’intégrité du journal d’audit chaîné');
