<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('payment_contracts')->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('total_due', 15, 2);
            $table->decimal('paid_at_signature', 15, 2);
            $table->decimal('balance', 15, 2);
            $table->json('charge_ids');
            $table->text('conditions');
            $table->json('signatories')->nullable();
            // draft → active (approuvé) → signed (version signée archivée) ; superseded ; cancelled
            $table->enum('status', ['draft', 'active', 'signed', 'superseded', 'cancelled'])->default('draft')->index();
            $table->string('signed_file_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['number', 'version']);
        });

        Schema::create('contract_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->timestamps();
            $table->unique(['payment_contract_id', 'sequence']);
        });

        // Registre des documents émis (numéro unique, statut, vérification par QR code).
        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->enum('type', ['receipt', 'statement', 'attestation', 'situation', 'contract', 'refund', 'compensation', 'carry_forward']);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->enum('status', ['provisional', 'final', 'cancelled'])->default('final');
            $table->char('verification_token', 40)->unique();
            $table->char('content_hash', 64);
            $table->json('summary');                  // éléments minimaux affichés à la vérification
            $table->json('content');                  // instantané figé du contenu à l'émission
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('exam_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_session_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->string('authority', 150);         // autorité ayant accordé la dérogation
            $table->date('granted_on');
            $table->date('valid_until');
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_waivers');
        Schema::dropIfExists('issued_documents');
        Schema::dropIfExists('contract_installments');
        Schema::dropIfExists('payment_contracts');
    }
};
