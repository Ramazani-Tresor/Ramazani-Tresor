<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Créances (obligations financières de l'étudiant).
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('exam_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);                 // montant initial (barème)
            $table->enum('origin', ['initial', 'carried_over', 'manual']);
            $table->foreignId('carried_from_charge_id')->nullable()->constrained('charges')->restrictOnDelete();
            $table->enum('status', ['active', 'cancelled'])->default('active')->index();
            // Clé anti-doublon : une seule créance par (étudiant, année, frais, session, origine reportée)
            $table->string('uniqueness_key', 120)->unique();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'academic_year_id', 'currency']);
        });

        Schema::create('charge_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('label', 80);
            $table->decimal('amount', 15, 2);
            $table->date('due_date')->index();
            $table->timestamps();
            $table->unique(['charge_id', 'sequence']);
        });

        // Réductions, bourses, exonérations, prises en charge, ajustements et transferts de report.
        Schema::create('charge_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('charge_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['scholarship', 'reduction', 'exemption', 'sponsorship', 'increase', 'decrease', 'transfer_out', 'schedule_revision']);
            // Montant signé : positif = diminue la créance, négatif = l'augmente.
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->string('sponsor', 150)->nullable();        // organisme de prise en charge
            $table->string('justification_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'reversed'])->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_adjustments');
        Schema::dropIfExists('charge_installments');
        Schema::dropIfExists('charges');
    }
};
