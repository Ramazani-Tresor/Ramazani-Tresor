<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lignes de relevé bancaire = preuve bancaire.
        Schema::create('statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('source_row');
            $table->date('operation_date');
            $table->date('value_date')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('reference_normalized', 120)->nullable()->index();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);                 // crédit (montant reçu)
            $table->char('currency', 3);
            // Empreinte anti double comptage entre relevés qui se chevauchent
            $table->char('fingerprint', 64)->unique();
            $table->enum('reliability', ['reliable', 'uncertain'])->default('reliable');
            $table->string('reliability_note')->nullable();
            $table->enum('review_status', ['not_required', 'pending', 'verified', 'rejected'])->default('not_required')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('matched_amount', 15, 2)->default(0);
            $table->enum('match_status', ['unmatched', 'partial', 'matched', 'ignored'])->default('unmatched')->index();
            $table->string('ignore_reason')->nullable();
            $table->timestamps();
            $table->index(['bank_account_id', 'operation_date']);
        });

        Schema::create('reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['auto_exact', 'manual']);
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('criteria')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active')->index();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_matches');
        Schema::dropIfExists('statement_lines');
    }
};
