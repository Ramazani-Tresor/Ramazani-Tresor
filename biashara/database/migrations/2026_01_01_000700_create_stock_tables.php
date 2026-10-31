<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_reference', 80)->nullable(); // n° facture / BL fournisseur
            $table->date('purchase_date');
            $table->enum('status', ['draft', 'validated', 'cancelled'])->default('draft')->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->boolean('paid_from_cash')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['open', 'validated', 'cancelled'])->default('open')->index();
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Stock théorique figé au moment du comptage (pas à l'ouverture), voir docs.
            $table->decimal('theoretical_quantity', 15, 3)->nullable();
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('difference', 15, 3)->nullable();
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->string('justification')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->unsignedBigInteger('last_movement_id')->default(0); // dernier mouvement connu au comptage
            $table->timestamps();
            $table->unique(['inventory_id', 'product_id']);
        });

        // Journal des mouvements : source de vérité du stock.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // initial, purchase, sale, sale_cancel, customer_return, customer_return_damaged,
            // supplier_return, inventory_adjustment, loss, damaged_writeoff
            $table->string('type', 40)->index();
            $table->enum('bucket', ['available', 'damaged'])->default('available');
            $table->decimal('quantity', 15, 3);       // signé
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('balance_after', 15, 3);  // solde du compartiment après mouvement
            $table->string('reason')->nullable();
            $table->nullableMorphs('source');         // document d'origine
            $table->string('source_number', 40)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
