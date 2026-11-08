<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paramétrage : années, structure organisationnelle, postes, catégories, grades, mandats, fonctions, types de situations, primes, aides sociales. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();          // 2026-2027
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('is_current')->default(false);
            $t->string('status', 20)->default('open'); // planned | open | closed (jamais supprimée)
            $t->timestamps();
        });
        // Structure organisationnelle hiérarchique (rectorat, facultés, départements, services…)
        Schema::create('org_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->string('type', 30);   // rectorate | vice_rectorate | sga | sgad | faculty | department | research_center | library | admin_service | technical_service | other
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('staff_categories', function (Blueprint $t) {
            $t->id();
            $t->string('family', 20); // academic | scientific | administrative
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('grades', function (Blueprint $t) {
            $t->id();
            $t->string('family', 20);
            $t->foreignId('staff_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->unsignedSmallInteger('rank')->default(0);          // ordre hiérarchique (indicatif, aucune promotion automatique)
            $t->boolean('has_mandates')->default(false);          // ex. assistant : premier / deuxième mandat
            $t->json('characteristics')->nullable();              // caractéristiques administratives et financières libres
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('mandates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->unsignedSmallInteger('sequence')->default(1);
            $t->unsignedSmallInteger('duration_months')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('job_functions', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20); // scientific | administrative | technical | other
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->boolean('is_responsibility')->default(false); // responsabilité administrative (doyen, chef de service…)
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('positions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained();
            $t->foreignId('job_function_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code', 40)->unique();
            $t->string('title');
            $t->unsignedSmallInteger('seats')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        // Types de situations administratives et leur règle financière paramétrable.
        Schema::create('situation_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name');
            $t->string('kind', 20);                   // leave | suspension | availability | reinstatement | end
            $t->string('resulting_status', 30);       // statut de l'agent pendant la situation
            $t->unsignedTinyInteger('prime_percent')->default(100); // % des primes dues pendant la situation (0 = suspendues)
            $t->boolean('closes_assignment')->default(false);
            $t->boolean('ends_employment')->default(false);   // retraite, démission : fin des droits ordinaires à la date d'effet
            $t->boolean('requires_end_date')->default(true);
            $t->unsignedSmallInteger('max_days')->nullable();
            $t->boolean('requires_approval')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('prime_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('periodicity', 20); // monthly | quarterly | semester | yearly | tranche | one_off
            $t->boolean('prorata')->default(true);     // calcul au prorata des jours ouvrant droit
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        // Taux : le plus spécifique (nombre de critères renseignés) l'emporte ; aucun montant codé en dur.
        Schema::create('prime_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prime_type_id')->constrained();
            $t->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $t->string('staff_family', 20)->nullable();
            $t->foreignId('staff_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_function_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('org_unit_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 14, 2);
            $t->string('currency', 3)->default('USD');
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('kinship_degrees', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->decimal('support_amount', 14, 2)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->text('conditions')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('support_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');                     // deuil, maladie, assistance exceptionnelle…
            $t->boolean('uses_kinship')->default(false);
            $t->decimal('default_amount', 14, 2)->nullable();
            $t->decimal('max_amount', 14, 2)->nullable();
            $t->string('currency', 3)->default('USD');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('document_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->boolean('visible_to_agent')->default(false); // téléchargeable dans l'espace personnel
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['document_types', 'support_types', 'kinship_degrees', 'prime_rates', 'prime_types', 'situation_types', 'positions', 'job_functions', 'mandates', 'grades', 'staff_categories', 'org_units', 'academic_years'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
