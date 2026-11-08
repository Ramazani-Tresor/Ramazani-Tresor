<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dossiers du personnel, pièces jointes, carrière, qualifications, affectations, mutations, situations, décès, demandes de correction. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('matricule', 40)->unique();
            $t->string('last_name');                  // nom
            $t->string('middle_name')->nullable();    // postnom
            $t->string('first_name')->nullable();     // prénom
            $t->string('sex', 1);                     // M | F
            $t->date('birth_date')->nullable();
            $t->string('birth_place')->nullable();
            $t->string('nationality')->default('Congolaise');
            $t->string('address')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('photo_path')->nullable();
            $t->string('marital_status', 20)->nullable(); // single | married | divorced | widowed
            $t->unsignedTinyInteger('children_count')->default(0);
            $t->text('family_notes')->nullable();
            $t->string('emergency_contact_name')->nullable();
            $t->string('emergency_contact_phone', 40)->nullable();
            $t->string('emergency_contact_relation')->nullable();
            $t->date('first_service_date')->nullable();
            $t->date('engagement_date')->nullable();
            $t->string('staff_family', 20);           // academic | scientific | administrative
            $t->foreignId('staff_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('mandate_id')->nullable()->constrained()->nullOnDelete();
            $t->date('mandate_start')->nullable();
            $t->date('mandate_end')->nullable();
            $t->string('assistant_track', 20)->nullable();      // teaching | research (assistants)
            $t->foreignId('admin_grade_id')->nullable()->constrained('grades')->nullOnDelete(); // catégorie administrative (assistants de recherche) : grade de la famille administrative
            $t->foreignId('job_function_id')->nullable()->constrained()->nullOnDelete(); // fonction principale
            $t->string('status', 30)->default('active'); // active | on_leave | available | suspended | transferred_out | deceased | retired | resigned | departed
            $t->date('status_since')->nullable();
            $t->date('rights_end_date')->nullable();     // fin des droits ordinaires (décès, mutation externe, départ)
            // Coordonnées bancaires : chiffrées en base (cast encrypted), visibles uniquement avec employees.bank
            $t->text('bank_name')->nullable();
            $t->text('bank_account')->nullable();
            $t->text('bank_holder')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['staff_family', 'status']);
            // Aucune suppression physique : pas de softDeletes, la suppression est interdite par le modèle.
        });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('reference')->nullable();
            $t->date('document_date')->nullable();
            $t->string('signing_authority')->nullable();
            $t->string('path');
            $t->string('mime', 80);
            $t->unsignedInteger('size');
            $t->string('sha256', 64);
            $t->nullableMorphs('related');            // lien éventuel vers la décision (promotion, mutation…)
            $t->foreignId('uploaded_by')->nullable();
            $t->timestamps();
        });
        // Carrière : nominations, promotions, renouvellements de mandat, reclassements, obtention du doctorat.
        Schema::create('career_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->string('type', 30);                   // engagement | nomination | promotion | mandate_renewal | reclassification | doctorate | function_change
            $t->foreignId('old_grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $t->foreignId('new_grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $t->foreignId('old_category_id')->nullable()->constrained('staff_categories')->nullOnDelete();
            $t->foreignId('new_category_id')->nullable()->constrained('staff_categories')->nullOnDelete();
            $t->foreignId('old_mandate_id')->nullable()->constrained('mandates')->nullOnDelete();
            $t->foreignId('new_mandate_id')->nullable()->constrained('mandates')->nullOnDelete();
            $t->date('new_mandate_end')->nullable();
            $t->date('effective_date');
            $t->date('decision_date');
            $t->string('decision_number');
            $t->string('signing_authority');
            $t->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $t->text('observations')->nullable();
            $t->string('status', 20)->default('draft'); // draft | validated | rejected | cancelled
            $t->foreignId('created_by')->nullable();
            $t->foreignId('validated_by')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'effective_date']);
        });
        Schema::create('qualifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->string('nature', 30);                 // licence | master | dea | research_thesis (mémoire de recherche) | dea_thesis (mémoire de DEA) | doctoral_thesis | doctorate | other
            $t->string('title')->nullable();          // intitulé du diplôme, du mémoire ou de la thèse
            $t->string('field')->nullable();          // domaine scientifique
            $t->string('institution')->nullable();
            $t->string('defense_university')->nullable();
            $t->date('defense_date')->nullable();
            $t->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $t->string('mention')->nullable();
            $t->string('supervisor')->nullable();
            $t->foreignId('org_unit_id')->nullable()->constrained()->nullOnDelete(); // faculté / département de rattachement au moment de la soutenance
            $t->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['nature', 'academic_year_id']);
        });
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('from_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->foreignId('org_unit_id')->constrained();
            $t->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_function_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_primary')->default(true);
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('reason')->nullable();
            $t->string('decision_number')->nullable();
            $t->string('authority')->nullable();
            $t->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $t->string('status', 20)->default('active'); // active | closed
            $t->string('closure_reason')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
            $t->index(['org_unit_id', 'status']);
        });
        Schema::create('mutations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->string('kind', 10);                    // internal | external
            $t->foreignId('from_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->foreignId('to_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->foreignId('to_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $t->string('origin_institution')->nullable();
            $t->string('destination_institution')->nullable();
            $t->string('reason');
            $t->date('decision_date');
            $t->date('effective_date');
            $t->string('decision_reference');
            $t->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $t->json('financial_snapshot')->nullable(); // situation financière à la date de départ
            $t->string('status', 20)->default('draft');
            $t->foreignId('created_by')->nullable();
            $t->foreignId('validated_by')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('administrative_situations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('situation_type_id')->constrained();
            $t->foreignId('extends_id')->nullable()->constrained('administrative_situations')->nullOnDelete(); // prolongation
            $t->date('start_date');
            $t->date('planned_end_date')->nullable();
            $t->date('actual_end_date')->nullable();
            $t->string('reason');
            $t->string('decision_reference')->nullable();
            $t->string('authority')->nullable();
            $t->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $t->string('status', 20)->default('requested'); // requested | approved | rejected | closed | cancelled
            $t->string('previous_status', 30)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('closed_by')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamp('alert_sent_at')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });
        Schema::create('deaths', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->unique()->constrained();
            $t->date('death_date');
            $t->string('death_place')->nullable();
            $t->text('cause')->nullable();            // chiffrée, accès restreint (deaths.cause)
            $t->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $t->string('contact_name')->nullable();
            $t->string('contact_phone', 40)->nullable();
            $t->string('contact_relation')->nullable();
            $t->json('financial_snapshot')->nullable();
            $t->string('status', 20)->default('draft');
            $t->foreignId('created_by')->nullable();
            $t->foreignId('validated_by')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->timestamps();
        });
        Schema::create('correction_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->string('field', 60);
            $t->text('current_value')->nullable();
            $t->text('requested_value');
            $t->text('justification');
            $t->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $t->string('status', 20)->default('pending'); // pending | accepted | rejected
            $t->text('response')->nullable();
            $t->foreignId('handled_by')->nullable();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['correction_requests', 'deaths', 'administrative_situations', 'mutations', 'assignments', 'qualifications', 'career_events', 'employee_documents', 'employees'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
