<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 40)->unique();
            $table->string('last_name', 100);
            $table->string('post_name', 100)->nullable();
            $table->string('first_name', 100);
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('promotion_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['new', 'returning', 'finalist']);
            $table->enum('state', ['active', 'cancelled'])->default('active');
            $table->date('enrolled_on');
            $table->foreignId('import_batch_id')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'promotion_id']);
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('label', 150);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->enum('type', ['students', 'bank_statement']);
            $table->string('original_name');
            $table->string('stored_path');               // fichier source conservé (disque privé)
            $table->char('file_hash', 64);               // SHA-256 du fichier
            $table->json('column_map')->nullable();
            $table->json('options')->nullable();
            $table->enum('status', ['uploaded', 'previewed', 'processing', 'completed', 'failed', 'cancelled'])->default('uploaded');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_valid')->default(0);
            $table->unsignedInteger('rows_error')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->json('report')->nullable();          // erreurs par ligne
            $table->foreignId('bank_account_id')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['type', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
    }
};
