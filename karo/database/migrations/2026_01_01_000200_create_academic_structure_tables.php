<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['faculty_id', 'code']);
        });

        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();     // LMD-L, LMD-M, DOC…
            $table->string('name', 100);
            $table->unsignedTinyInteger('duration_years')->default(3);
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) { // filières
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('cycle_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->string('code', 40)->unique();      // ex. DROIT-L1
            $table->string('level', 20);              // Bac1, Bac2, Bac3, M1, M2…
            $table->string('name', 150);
            $table->boolean('is_final')->default(false); // promotion finale (entérinement du diplôme)
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();      // 2025-2026
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('status', ['planned', 'open', 'closed'])->default('planned')->index();
            $table->boolean('is_current')->default(false);
            $table->foreignId('previous_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closing_report')->nullable();
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'number']);
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('semester');                  // 1 ou 2
            $table->enum('type', ['ordinary', 'resit']);              // session ordinaire / rattrapage
            $table->string('name', 120);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // Conditions financières d'accès (paramétrables)
            $table->decimal('threshold_percent', 5, 2)->default(100); // % minimum des frais concernés réglé
            $table->date('payment_deadline')->nullable();
            $table->boolean('require_bank_confirmation')->default(false);
            $table->json('fee_category_ids')->nullable();             // frais pris en compte pour l'accès à la session
            $table->timestamps();
            $table->unique(['academic_year_id', 'semester', 'type']);
        });

        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            // Périmètre : NULL = toutes les facultés / toutes les années
            $table->foreignId('faculty_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('cycles');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('faculties');
    }
};
