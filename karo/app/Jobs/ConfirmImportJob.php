<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\User;
use App\Services\StatementImportService;
use App\Services\StudentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Traitement asynchrone des imports volumineux (file « database », worker local). */
class ConfirmImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $batchId, public int $userId, public array $options = []) {}

    public function handle(): void
    {
        $batch = ImportBatch::query()->findOrFail($this->batchId);
        $user = User::query()->findOrFail($this->userId);
        try {
            if ($batch->type === 'students') {
                app(StudentImportService::class)->confirm($batch, $user, (bool) ($this->options['skip_invalid'] ?? false), (bool) ($this->options['generate_charges'] ?? false));
            } else {
                app(StatementImportService::class)->confirm($batch, $user);
            }
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'report' => array_merge((array) $batch->report, ['__erreur' => mb_substr($e->getMessage(), 0, 500)])]);
            throw $e;
        }
    }
}
