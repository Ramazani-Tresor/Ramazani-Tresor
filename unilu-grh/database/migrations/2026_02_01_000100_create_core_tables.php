<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paramètres, séquences, rôles et permissions, journal d'audit, historique des modifications, notifications, jetons d'API. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('type')->unique();
            $t->string('label');
            $t->string('prefix', 20);
            $t->unsignedInteger('next_number')->default(1);
            $t->unsignedTinyInteger('padding')->default(4);
            $t->boolean('yearly_reset')->default(true);
            $t->unsignedSmallInteger('current_year')->nullable();
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('module');
            $t->boolean('is_sensitive')->default(false);
            $t->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
        // Attribution d'un rôle avec périmètre facultatif : une unité (et ses sous-unités) et/ou une famille de personnel.
        Schema::create('user_role_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('org_unit_id')->nullable();
            $t->string('staff_family', 20)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'role_id', 'org_unit_id', 'staff_family'], 'ura_unique');
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('action', 80)->index();
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->string('description', 500)->nullable();
            $t->text('reason')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->string('device_id', 64)->nullable();
            $t->string('previous_hash', 64)->nullable();
            $t->string('hash', 64);
            $t->timestamp('created_at')->nullable();
            $t->index(['auditable_type', 'auditable_id']);
        });
        // Historique champ par champ des dossiers (lisible par les gestionnaires, distinct du journal d'audit).
        Schema::create('record_changes', function (Blueprint $t) {
            $t->id();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->unsignedBigInteger('employee_id')->nullable()->index();
            $t->string('field', 80);
            $t->text('old_value')->nullable();
            $t->text('new_value')->nullable();
            $t->foreignId('user_id')->nullable();
            $t->string('context', 120)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('api_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('token_hash', 64)->unique();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['api_tokens', 'notifications', 'record_changes', 'audit_logs', 'user_role_assignments', 'permission_role', 'permissions', 'roles', 'document_sequences', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
