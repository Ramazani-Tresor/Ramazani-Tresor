<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            // Identifiant généré par l'appareil : clé d'idempotence (double clic, re-tentative, synchro).
            $table->uuid('client_uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['validated', 'cancelled'])->default('validated')->index();
            $table->enum('origin', ['online', 'offline'])->default('online');
            $table->string('device_id', 64)->nullable();
            $table->decimal('subtotal', 15, 2);        // somme des lignes brutes HT (prix x qté)
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2);           // net à payer TTC
            $table->decimal('amount_paid', 15, 2)->default(0);     // encaissé imputé à la vente
            $table->decimal('amount_due', 15, 2)->default(0);      // créance restante
            $table->decimal('amount_received', 15, 2)->default(0); // remis par le client (espèces incluses)
            $table->decimal('change_given', 15, 2)->default(0);
            $table->decimal('returned_total', 15, 2)->default(0);  // valeur des retours
            $table->decimal('refunded_total', 15, 2)->default(0);  // montants remboursés
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 15, 4)->default(1);
            $table->unsignedInteger('print_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sold_at')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'sold_at']);
            $table->index(['customer_id', 'amount_due']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Instantané des données au moment de la vente (les reçus ne changent jamais).
            $table->string('product_reference', 50);
            $table->string('product_name', 190);
            $table->enum('product_type', ['product', 'service']);
            $table->string('unit_symbol', 15);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('tax_name', 80)->nullable();
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);   // (prix x qté - remise) + taxe
            $table->decimal('unit_cost', 15, 4)->default(0); // CMP de sortie, pour la marge historique
            $table->decimal('returned_quantity', 15, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('customer_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('paid_at')->index();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->string('reason');
            $table->decimal('total_value', 15, 2);     // valeur TTC des articles retournés
            $table->decimal('due_reduction', 15, 2)->default(0); // part imputée sur la créance
            $table->decimal('refund_amount', 15, 2)->default(0); // part remboursée au client
            $table->boolean('is_cancellation')->default(false);
            $table->timestamp('returned_at')->index();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->enum('condition', ['resaleable', 'damaged']);
            $table->decimal('unit_value', 15, 4); // valeur TTC unitaire nette de remise
            $table->decimal('line_value', 15, 2);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
        });

        // Paiements : encaissements de vente, règlements de créance et remboursements.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['sale', 'debt_settlement', 'refund']);
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_settlement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2); // en devise principale ; négatif pour un remboursement
            // Devise réellement remise et taux appliqué (jamais additionnés entre devises).
            $table->char('currency', 3);
            $table->decimal('currency_amount', 15, 2);
            $table->decimal('exchange_rate', 15, 4)->default(1);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->index();
            $table->timestamps();
            $table->index(['kind', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('customer_settlements');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
