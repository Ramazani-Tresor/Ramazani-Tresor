<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description')->nullable();
            $table->string('color', 9)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('symbol', 15);
            $table->boolean('allows_decimal')->default(false);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('name', 190)->index();
            $table->enum('type', ['product', 'service'])->default('product');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('photo_path')->nullable();
            $table->decimal('cost_price', 15, 2)->default(0);      // coût de référence saisi
            $table->decimal('average_cost', 15, 4)->default(0);    // CMP mobile, mis à jour par les entrées
            $table->decimal('sale_price', 15, 2);
            $table->decimal('alert_threshold', 15, 3)->default(0);
            // Quantités dénormalisées, toujours modifiées dans la même transaction qu'un mouvement.
            $table->decimal('stock_quantity', 15, 3)->default(0);
            $table->decimal('damaged_quantity', 15, 3)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
    }
};
