<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 60);
            $table->string('symbol', 6);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Taux de conversion approuvés : seule source autorisée pour une opération entre devises.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 18, 6);
            $table->date('effective_on');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('source', 150)->nullable();
            $table->timestamps();
            $table->unique(['from_currency', 'to_currency', 'effective_on']);
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->string('account_number', 60)->unique();
            $table->string('label', 150);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label', 150);
            $table->enum('nature', ['mandatory', 'conditional']);
            // enrollment : générée à l'inscription ; exam_session : à l'inscription à une session ; manual : affectation explicite
            $table->enum('trigger', ['enrollment', 'exam_session', 'manual']);
            $table->json('applicable_statuses')->nullable();   // ["new","returning","finalist"] ; NULL = tous
            $table->boolean('final_promotions_only')->default(false);
            $table->unsignedTinyInteger('session_semester')->nullable();
            $table->enum('session_type', ['ordinary', 'resit'])->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedSmallInteger('priority')->default(100); // ordre d'imputation proposé
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_account_fee_category', function (Blueprint $table) {
            $table->foreignId('fee_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->primary(['fee_category_id', 'bank_account_id']);
        });

        // Barèmes versionnés : montant par année et critères (le plus spécifique s'applique).
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('replaces_id')->nullable()->constrained('fee_schedules')->nullOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('student_status', ['new', 'returning', 'finalist'])->nullable();
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->date('effective_from');
            $table->enum('status', ['draft', 'active', 'superseded', 'rejected'])->default('draft')->index();
            $table->boolean('apply_to_existing')->default(false);
            $table->string('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['fee_category_id', 'academic_year_id', 'status']);
        });

        Schema::create('fee_schedule_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('label', 80);
            $table->enum('mode', ['amount', 'percent']);
            $table->decimal('value', 15, 2);     // montant ou pourcentage saisi
            $table->decimal('amount', 15, 2);    // montant calculé
            $table->date('due_date');
            $table->timestamps();
            $table->unique(['fee_schedule_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedule_installments');
        Schema::dropIfExists('fee_schedules');
        Schema::dropIfExists('bank_account_fee_category');
        Schema::dropIfExists('fee_categories');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
