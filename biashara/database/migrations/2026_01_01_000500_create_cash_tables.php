<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->decimal('opening_amount', 15, 2);
            $table->timestamp('opened_at');
            $table->decimal('expected_cash', 15, 2)->nullable();      // calculé à la clôture
            $table->decimal('counted_cash', 15, 2)->nullable();       // compté physiquement
            $table->decimal('difference', 15, 2)->nullable();         // compté - théorique
            $table->decimal('electronic_total', 15, 2)->nullable();   // paiements non espèces de la session
            $table->text('closing_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            // Garantit au plus une session ouverte par caissier (NULL pour les sessions clôturées).
            $table->unsignedBigInteger('open_lock')->nullable()->unique();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            // opening, sale, debt_settlement, refund, expense, supplier_payment, deposit, withdrawal
            $table->string('type', 30)->index();
            $table->decimal('amount', 15, 2); // signé : + entrée, - sortie
            $table->string('description')->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
