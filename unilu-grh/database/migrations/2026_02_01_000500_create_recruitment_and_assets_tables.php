<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recrutement et engagement ; patrimoine immobilier et mobilier, mouvements et inventaires. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->string('last_name');
            $t->string('middle_name')->nullable();
            $t->string('first_name')->nullable();
            $t->string('sex', 1);
            $t->date('birth_date')->nullable();
            $t->string('birth_place')->nullable();
            $t->string('nationality')->default('Congolaise');
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
            $t->string('staff_family', 20);
            $t->foreignId('staff_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('org_unit_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_function_id')->nullable()->constrained()->nullOnDelete();
            $t->string('highest_degree')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('submitted'); // submitted | under_review | validated | rejected | engaged
            $t->string('decision_number')->nullable();
            $t->date('decision_date')->nullable();
            $t->date('engagement_date')->nullable();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('reviewed_by')->nullable();
            $t->foreignId('engaged_by')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('candidate_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('candidate_id')->constrained();
            $t->string('title');
            $t->string('path');
            $t->string('mime', 80);
            $t->unsignedInteger('size');
            $t->string('sha256', 64);
            $t->foreignId('uploaded_by')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_categories', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 12);                    // immovable | movable
            $t->string('code', 30)->unique();
            $t->string('name');                        // bâtiment, salle de cours, ordinateur, véhicule…
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();          // code patrimonial
            $t->foreignId('asset_category_id')->constrained();
            $t->string('designation');
            $t->foreignId('parent_id')->nullable()->constrained('assets')->nullOnDelete(); // local situé dans un bâtiment…
            $t->string('location')->nullable();
            $t->string('brand_model')->nullable();
            $t->string('serial_number')->nullable();
            $t->unsignedInteger('quantity')->default(1);
            $t->date('acquisition_date')->nullable();
            $t->string('acquisition_mode', 20)->nullable(); // purchase | construction | donation | transfer | other
            $t->decimal('value', 16, 2)->nullable();
            $t->string('currency', 3)->default('USD');
            $t->string('condition', 20)->default('good'); // new | good | fair | poor | out_of_order
            $t->string('status', 20)->default('in_service'); // in_service | reformed | scrapped
            $t->foreignId('org_unit_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained();
            $t->string('type', 20);                   // entry | assignment | transfer | condition | reform | scrap | inventory
            $t->foreignId('from_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->foreignId('to_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->string('old_condition', 20)->nullable();
            $t->string('new_condition', 20)->nullable();
            $t->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->date('movement_date');
            $t->string('reference')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('inventory_id')->nullable();
            $t->foreignId('user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('inventories', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->string('label');
            $t->foreignId('org_unit_id')->nullable()->constrained()->nullOnDelete();
            $t->date('started_on');
            $t->date('closed_on')->nullable();
            $t->string('status', 20)->default('open'); // open | closed
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('inventory_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inventory_id')->constrained();
            $t->foreignId('asset_id')->constrained();
            $t->boolean('found')->default(true);
            $t->string('observed_condition', 20)->nullable();
            $t->string('observed_location')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('checked_by')->nullable();
            $t->timestamps();
            $t->unique(['inventory_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        foreach (['inventory_lines', 'inventories', 'asset_movements', 'assets', 'asset_categories', 'candidate_documents', 'candidates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
