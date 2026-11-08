<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->nullableMorphs('auditable');
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64)->unique();
            $table->string('label', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        // Opérations hors ligne reçues : une ligne par identifiant d'appareil (anti-doublon).
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('type', 30); // sale
            $table->string('device_id', 64)->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('payload');
            $table->enum('status', ['synced', 'conflict', 'rejected'])->index();
            $table->json('conflicts')->nullable();
            $table->string('message')->nullable();
            $table->nullableMorphs('result');
            $table->timestamp('created_offline_at')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('audit_logs');
    }
};
