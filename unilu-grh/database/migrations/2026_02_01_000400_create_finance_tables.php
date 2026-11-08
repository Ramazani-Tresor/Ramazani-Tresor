<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Primes (droits, ajustements, paiements et acomptes) et soutiens sociaux. Aucune suppression : contrepassation et régularisation. */
return new class extends Migration
{
    public function up(): void
    {
        // Droit à une prime pour une période : montant théorique calculé, jamais effacé.
        Schema::create('prime_entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('prime_type_id')->constrained();
            $t->foreignId('prime_rate_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $t->string('period_label', 40);           // 2026-11, T1 2026-2027…
            $t->date('period_start');
            $t->date('period_end');
            $t->decimal('base_amount', 14, 2);        // taux applicable pour la période complète
            $t->decimal('amount', 14, 2);             // montant calculé à la génération (prorata et règles de situation)
            $t->string('currency', 3);
            $t->decimal('eligible_ratio', 7, 6)->default(1);
            $t->json('calculation')->nullable();      // détail des jours et des règles appliquées
            $t->string('status', 20)->default('due'); // due | suspended (montant nul du fait d'une situation) | cancelled
            $t->string('uniqueness_key')->unique();   // agent + type + période : pas de doublon
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'period_start']);
        });
        // Ajustement signé du droit (décès, mutation enregistrés après coup, correction) : jamais de modification silencieuse.
        Schema::create('prime_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prime_entitlement_id')->constrained();
            $t->decimal('amount', 14, 2);             // négatif = réduction du droit
            $t->string('reason');
            $t->string('source', 30)->default('manual'); // manual | death | mutation | situation | recalculation
            $t->nullableMorphs('origin');
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });
        // Mouvements de paiement : acompte, paiement, régularisation (trop-perçu), contrepassation.
        Schema::create('prime_payments', function (Blueprint $t) {
            $t->id();
            $t->string('number', 40)->unique();
            $t->foreignId('prime_entitlement_id')->constrained();
            $t->foreignId('employee_id')->constrained();
            $t->string('kind', 20);                   // advance | payment | regularization | reversal
            $t->decimal('amount', 14, 2);             // signé : négatif pour une récupération ou une contrepassation
            $t->string('currency', 3);
            $t->date('payment_date');
            $t->string('mode', 20)->nullable();       // bank_transfer | cash | mobile_money | check | offset
            $t->string('reference')->nullable();
            $t->string('beneficiary')->nullable();    // ayant droit (après décès)
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('pending'); // pending | validated | rejected | reversed
            $t->foreignId('reverses_id')->nullable()->constrained('prime_payments')->nullOnDelete();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('validated_by')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });
        Schema::create('social_supports', function (Blueprint $t) {
            $t->id();
            $t->string('number', 40)->unique();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('support_type_id')->constrained();
            $t->foreignId('kinship_degree_id')->nullable()->constrained()->nullOnDelete();
            $t->string('deceased_name')->nullable();
            $t->date('event_date')->nullable();
            $t->text('description')->nullable();
            $t->decimal('amount_requested', 14, 2)->nullable();
            $t->decimal('amount_granted', 14, 2)->nullable();
            $t->string('currency', 3)->default('USD');
            $t->string('decision_reference')->nullable();
            $t->string('beneficiary')->nullable();
            $t->string('status', 20)->default('requested'); // requested | approved | rejected | paid
            $t->date('paid_on')->nullable();
            $t->string('payment_reference')->nullable();
            $t->foreignId('death_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('paid_by')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('social_support_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_support_id')->constrained();
            $t->foreignId('employee_document_id')->constrained();
        });
    }

    public function down(): void
    {
        foreach (['social_support_documents', 'social_supports', 'prime_payments', 'prime_adjustments', 'prime_entitlements'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
