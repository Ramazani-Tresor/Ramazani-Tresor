<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paiement enregistré (déclaration d'un bordereau). La preuve bancaire (relevé)
        // est une entité distincte (statement_lines) ; le lien est le rapprochement.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('client_uuid')->unique();             // clé d'idempotence (formulaire, PWA)
            $table->enum('type', ['bank_deposit', 'carry_forward'])->default('bank_deposit');
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bank_reference', 80)->nullable();
            $table->string('bank_reference_normalized', 80)->nullable()->index();
            $table->date('deposit_date')->nullable();          // date bancaire du versement
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('depositor_name', 150)->nullable();
            $table->string('proof_path')->nullable();          // justificatif (disque privé)
            $table->string('proof_sha256', 64)->nullable();
            // Cycle interne : submitted → checked → validated ; rejected ; reversed (contrepassation)
            $table->enum('status', ['submitted', 'checked', 'validated', 'rejected', 'reversed'])->default('submitted')->index();
            // Confirmation par une source bancaire (indépendante de la validation interne)
            $table->enum('bank_status', ['unconfirmed', 'partial', 'confirmed', 'not_applicable'])->default('unconfirmed')->index();
            $table->decimal('bank_confirmed_amount', 15, 2)->default(0);
            $table->json('anomalies')->nullable();             // contrôles automatiques à la saisie
            $table->foreignId('origin_payment_id')->nullable()->constrained('payments')->restrictOnDelete(); // report
            $table->foreignId('origin_credit_operation_id')->nullable();
            $table->enum('channel', ['web', 'offline_sync', 'system'])->default('web');
            $table->string('device_id', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'academic_year_id', 'currency']);
            $table->index(['bank_account_id', 'deposit_date']);
        });

        // Historique des transitions de statut (qui, quand, pourquoi).
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Affectation d'une partie d'un paiement à une créance. Le paiement reste unique.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['direct', 'compensation'])->default('direct');
            $table->decimal('amount', 15, 2);                  // dans la devise du paiement
            $table->decimal('charge_amount', 15, 2);           // dans la devise de la créance
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->restrictOnDelete();
            // proposed (avant validation du paiement) → active ; reversed (contrepassation)
            $table->enum('status', ['proposed', 'active', 'reversed'])->default('proposed')->index();
            $table->foreignId('credit_operation_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['charge_id', 'status']);
        });

        // Utilisation d'un excédent : compensation, remboursement, report, conservation.
        Schema::create('credit_operations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_payment_id')->constrained('payments')->restrictOnDelete();
            $table->enum('type', ['compensation', 'refund', 'carry_forward']);
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->foreignId('target_charge_id')->nullable()->constrained('charges')->restrictOnDelete();
            $table->foreignId('target_academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
            $table->string('refund_method', 80)->nullable();
            $table->string('refund_reference', 80)->nullable();
            // pending réserve le montant (empêche un double usage) ; executed le consomme
            $table->enum('status', ['pending', 'executed', 'rejected'])->default('pending')->index();
            $table->string('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_operations');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
